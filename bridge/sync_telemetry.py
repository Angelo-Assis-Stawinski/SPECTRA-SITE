#!/usr/bin/env python3
import configparser,csv,json,os,sys,time,hashlib,mimetypes,urllib.request,uuid,math,contextlib
from pathlib import Path
HERE=Path(__file__).resolve().parent
CFG=json.loads((HERE/'config.json').read_text('utf-8')) if (HERE/'config.json').exists() else json.loads((HERE/'config.example.json').read_text('utf-8'))
ROOT=Path(CFG['spectra_root']); TEL=Path(CFG.get('telemetry_dir') or ROOT/'DATA'/'WEB_TELEMETRY'); STATE=HERE/'bridge_state.json'
def read_ini(p):
    cp=configparser.ConfigParser(interpolation=None,strict=False);cp.optionxform=str
    if p.exists():
        with p.open('r',encoding='utf-8',errors='ignore') as f: cp.read_file(f)
    return {s:{k:v for k,v in cp.items(s)} for s in cp.sections()}
def csv_grid(p):
    if not p.exists(): return None
    rows=[]
    with p.open('r',encoding='utf-8',errors='ignore') as f:
        for r in csv.reader(f):
            if r and not r[0].startswith('#'):
                try:
                    values=[float(x) for x in r]
                    if all(math.isfinite(x) for x in values): rows.append(values)
                except: pass
    if not rows:return None
    if len(rows)>180 or len(rows[0])>360 or any(len(row)!=len(rows[0]) for row in rows): raise ValueError('Grade CSV inválida')
    return {'width':len(rows[0]),'height':len(rows),'values':[v for r in rows for v in r]}
def spectrum(p):
    vals=[]
    if p.exists():
        with p.open('r',encoding='utf-8',errors='ignore') as f:
            for r in csv.DictReader(f):
                try:
                    value=float(r.get('magnitude','0'));vals.append(value if math.isfinite(value) else 0.0)
                except: vals.append(0.0)
    return vals
def large_scale(ref):
    cfg=read_ini(ref).get('LargeScale',{}) if ref.exists() else {}; rel=cfg.get('RelativePath','')
    p=(ROOT/Path(rel.replace('\\','/'))).resolve() if rel else None
    if p and not p.is_relative_to(ROOT.resolve()): raise ValueError('Referência de larga escala fora da estação')
    if not p or not p.exists():
        cand=[]
        scans=ROOT/'DATA'/'SCANS'
        if scans.exists():
            for q in scans.rglob('*.csv'):
                try:
                    with q.open('r',encoding='utf-8',errors='ignore') as f:
                        head=''.join([f.readline() for _ in range(8)])
                    if 'SPECTRA_LARGE_SCALE_SECTION_V1' in head: cand.append(q)
                except: pass
        p=max(cand,key=lambda q:q.stat().st_mtime) if cand else None
    if not p or not p.exists(): return None
    W,H=360,180; sums=[0.0]*(W*H);cnt=[0]*(W*H)
    with p.open('r',encoding='utf-8',errors='ignore') as f:
        for row in csv.reader(line for line in f if not line.startswith('#')):
            if row and row[0]=='x':continue
            try:
                lon=float(row[2]);lat=float(row[3]);v=float(row[4]);x=min(W-1,max(0,int((lon+180)/360*W)));y=min(H-1,max(0,int((lat+90)/180*H)));i=y*W+x
                if math.isfinite(v): sums[i]+=v;cnt[i]+=1
            except: pass
    vals=[sums[i]/cnt[i] if cnt[i] else 0.0 for i in range(W*H)]
    return {'width':W,'height':H,'values':vals}
def post_json(payload):
    u=CFG['server_url']+'?action=bridge_upload';data=json.dumps(payload,ensure_ascii=False,separators=(',',':')).encode();req=urllib.request.Request(u,data=data,headers={'Content-Type':'application/json','X-Telemetry-Token':CFG['upload_token']});return urllib.request.urlopen(req,timeout=60).read()
def upload_file(p):
    boundary='----SPECTRA'+uuid.uuid4().hex;data=p.read_bytes();rel=str(p.relative_to(ROOT)).replace('\\','/');name=hashlib.sha1(rel.encode()).hexdigest()[:10]+'_'+p.name;head=(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: {mimetypes.guess_type(name)[0] or "application/octet-stream"}\r\n\r\n').encode();body=head+data+f'\r\n--{boundary}--\r\n'.encode();req=urllib.request.Request(CFG['server_url']+'?action=bridge_file',data=body,headers={'Content-Type':'multipart/form-data; boundary='+boundary,'X-Telemetry-Token':CFG['upload_token']});return json.loads(urllib.request.urlopen(req,timeout=120).read())
def recent_files():
    exts={e.lower() for e in CFG.get('public_extensions',[])};out=[]
    for base in [ROOT/'DATA'/'EXPORT',ROOT/'DATA'/'SCANS',ROOT/'DATA'/'SESSIONS']:
        if not base.exists():continue
        for p in base.rglob('*'):
            if p.is_file() and p.suffix.lower() in exts and p.stat().st_size<=CFG.get('max_public_file_mb',20)*1024*1024: out.append(p)
    return sorted(out,key=lambda p:p.stat().st_mtime,reverse=True)[:CFG.get('recent_file_count',20)]
def main():
    if CFG['upload_token'].startswith('COLE_'): raise SystemExit('Configure bridge/config.json com o token do painel admin.')
    state=json.loads(STATE.read_text('utf-8')) if STATE.exists() else {'last_generation':'','count':0,'mean':[],'history':[],'log_pos':0,'files':{}}
    if (TEL/'snapshot.lock').exists(): return
    st=read_ini(TEL/'status.ini');generation=st.get('Telemetry',{}).get('Generation','')
    sync_path=TEL/'sync.request'; logs_path=TEL/'logs.request'
    request_signature=lambda p: (p.stat().st_mtime_ns,p.stat().st_size) if p.exists() else None
    sync_sig=request_signature(sync_path); logs_sig=request_signature(logs_path)
    if not generation: return
    if generation and generation==state.get('last_generation') and sync_sig is None and logs_sig is None: return
    latest=spectrum(TEL/'spectrum_latest.csv')
    if latest and generation!=state.get('last_generation'):
        c=state.get('count',0);mean=state.get('mean',[])
        if len(mean)!=len(latest): c=0;mean=[0.0]*len(latest)
        mean=[mean[i]+(latest[i]-mean[i])/(c+1) for i in range(len(latest))];c+=1;state['count']=c;state['mean']=mean;state['history']=(state.get('history',[])+[mean])[-64:]
    files=[]
    for p in recent_files():
        key=str(p);sig=f'{p.stat().st_size}:{int(p.stat().st_mtime)}';meta=state.get('files',{}).get(key,{})
        if isinstance(meta,str): meta={'sig':meta,'public':p.name}
        public=meta.get('public',p.name)
        if meta.get('sig')!=sig:
            try:
                public=upload_file(p).get('public_name',p.name);state.setdefault('files',{})[key]={'sig':sig,'public':public}
            except Exception as e: raise RuntimeError('Falha ao enviar '+p.name+': '+str(e)) from e
        files.append({'name':str(p.relative_to(ROOT)),'public_name':public,'size':p.stat().st_size,'modified':time.strftime('%Y-%m-%d %H:%M:%S',time.localtime(p.stat().st_mtime))})
    log=ROOT/'LOGS'/'spectra.log';auto=None
    if log.exists():
        pos=min(state.get('log_pos',0),log.stat().st_size)
        with log.open('rb') as f:
            f.seek(pos);raw=f.read(65536);state['log_pos']=f.tell()
        try: new=raw.decode('utf-8')
        except UnicodeDecodeError: new=raw.decode('cp1252',errors='replace')
        if new.strip(): auto={'key':str(log.stat().st_mtime_ns)+':'+str(pos)+':'+str(state['log_pos']),'title':'Sincronização automática '+time.strftime('%d/%m/%Y %H:%M'),'body':'Últimos eventos recebidos da estação:\n'+new[-12000:]}
    config_full=read_ini(TEL/'config_snapshot.dat'); config_full.update(read_ini(TEL/'runtime_config.ini'))
    config=json.loads(json.dumps(config_full))
    for sec,key in [('Rede','IPMestre'),('Sincronismo','IPSincronizadora'),('GPS','PortaCOM')]:
        if sec in config and key in config[sec]: config[sec][key]='[oculto no site público]'
    payload={'schema':1,'sync_id':generation or str(int(time.time())),'generated_at':time.strftime('%Y-%m-%d %H:%M:%S'),'status':st.get('Telemetry',{}),'heatmap':csv_grid(TEL/'heatmap.csv'),'large_scale':large_scale(TEL/'large_scale.ref'),'config':config,'config_private':config_full,'files':files,'spectrum':{'latest':latest,'mean':state.get('mean',[]),'history':state.get('history',[])},'auto_log':auto}
    if (TEL/'snapshot.lock').exists() or read_ini(TEL/'status.ini').get('Telemetry',{}).get('Generation')!=generation:
        return # Snapshot trocado durante a leitura: não publicar mistura de gerações.
    response=json.loads(post_json(payload))
    if not response.get('ok'): raise RuntimeError(response.get('error','Servidor recusou a telemetria'))
    state['last_generation']=generation
    tmp=STATE.with_suffix('.tmp');tmp.write_text(json.dumps(state,ensure_ascii=False,allow_nan=False),encoding='utf-8');os.replace(tmp,STATE)
    for request,sig in [(sync_path,sync_sig),(logs_path,logs_sig)]:
        if sig is not None and request_signature(request)==sig:
            try: request.unlink()
            except FileNotFoundError: pass
if __name__=='__main__':
    # Uma única execução por vez; o escalonador deve aguardar a anterior.
    lock=HERE/'bridge_running.lock'
    try: fd=os.open(lock,os.O_CREAT|os.O_EXCL|os.O_WRONLY)
    except FileExistsError: raise SystemExit('Ponte já em execução; se ela foi encerrada abruptamente, remova bridge_running.lock.')
    try:
        os.close(fd);main()
    finally:
        lock.unlink(missing_ok=True)

