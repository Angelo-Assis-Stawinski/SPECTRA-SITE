import math,random, pathlib
p=pathlib.Path('/mnt/data/science_work/SPECTRA_5.0.1_GALAXIA_GCC_CORRIGIDO/CSVs_DE_TESTE/03_CIENCIA_SINTETICA')
p.mkdir(parents=True,exist_ok=True)
rng=random.Random(134055)
files=[]
for section in range(4):
 f=(p/('galaxia_science_%02d.csv'%(section+1))).open('w',newline='')
 f.write('# SPECTRA_LARGE_SCALE_SECTION_V1\n# Source=SIMULACAO_TESTE_NAO_OBSERVACIONAL\n# CoordinateSystem=GALACTIC_LB\n# CoordinatesValid=1\n# DistanceInformation=PROVIDED_SYNTHETIC\n# DistanceUnit=kpc\n# DistanceOrigin=HELIOCENTRIC\n# DistanceKind=SYNTHETIC\n# GlobalWidth=360\n# GlobalHeight=181\n# OffsetX=%d\n# OffsetY=0\n# Width=90\n# Height=181\n# GlobalLonStartDeg=-180.0\n# GlobalLonStepDeg=1.0\n# GlobalLatStartDeg=-90.0\n# GlobalLatStepDeg=1.0\nx,y,galactic_lon_deg,galactic_lat_deg,intensity,distance_kpc,distance_sigma_kpc\n' %(section*90))
 files.append(f)
n=0
for i in range(132000):
 # Four logarithmic spiral arms in GC frame, with fine positional scatter.
 if i % 7 == 0:
  radius=abs(rng.gauss(0,1))*1.35
  phi=rng.random()*2*math.pi
  z=rng.gauss(0,0.55)
  intensity=1.4+2.1*rng.random()
 else:
  arm=i%4
  radius=2.0+13.0*rng.random()
  phi=3.1*math.log(max(radius,0.1)/2.0)+arm*math.pi/2 + rng.gauss(0,0.07)
  radius+=rng.gauss(0,0.10)
  z=rng.gauss(0,0.095+radius*0.007)
  intensity=0.25+1.4*rng.random()
 gx=radius*math.cos(phi); gy=radius*math.sin(phi)
 # Sun at (8.2,0,0) kpc in artificial Galactocentric frame.
 hx=8.2-gx; hy=gy; hz=z
 d=math.sqrt(hx*hx+hy*hy+hz*hz)
 if d<0.05:continue
 lon=math.degrees(math.atan2(hy,hx));lat=math.degrees(math.atan2(hz,math.hypot(hx,hy)))
 ix=int(math.floor(lon+180.0+0.5))%360;iy=max(0,min(180,int(math.floor(lat+90.0+0.5))))
 sect=ix//90;lx=ix-sect*90
 sigma=max(.02,d*(.025+.05*rng.random()))
 files[sect].write('%d,%d,%.7f,%.7f,%.6f,%.7f,%.7f\n'%(lx,iy,lon,lat,intensity,d,sigma))
 n+=1
for f in files:f.close()
(p/'COMO_USAR.md').write_text('''# Reconstrucao cientifica: CSVs SINTETICOS (nao sao observacoes)\n\nSelecione os quatro arquivos `galaxia_science_01..04.csv` juntos. Na janela de\nInterferometria de Larga Escala, habilite o mapa 3D e escolha `Cientifica (com distancia)`.\nCada linha e um objeto simulado, com longitude/latitude/d em kpc e incerteza\nnominal artificial. A estrutura espiral aqui foi construida pelo gerador, nao\nfoi descoberta pelo SPECTRA. O 3D cientifico so reproduz a geometria desses\nvalores, nao a preenche com estrelas ou bracos imaginados.\n\nOs antigos CSVs sem `distance_kpc` continuam validos para 2D e modo Artistico;\neles nao produzem pontos no modo Cientifico.\n\nColunas: x,y,galactic_lon_deg,galactic_lat_deg,intensity,distance_kpc,\ndistance_sigma_kpc (opcional). Cabeçalhos obrigatorios: # CoordinateSystem=GALACTIC_LB,\n# DistanceUnit=kpc e # DistanceOrigin=HELIOCENTRIC; alem dos metadados originais.\n\nObservacao: tratar uma distancia estimada por modelo como se fosse medicao\nintroduz vies cientifico. Catalogos reais precisam validar unidades, sistema\nde coordenadas, metodo de inferencia de distancia, incerteza e selecao.\n''',encoding='utf-8')
print('CSV rows',n)
