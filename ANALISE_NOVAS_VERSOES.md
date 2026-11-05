# Análise das novas versões do S.P.E.C.T.R.A

Data: 20/10/2026. Base do site: histórico até 4.6.11. Anexo: paraGPT.zip.

Método: comparação sequencial dos fontes. Numeração e bytes dos programas foram preservados; identificação da pasta tem precedência quando o arquivo mantém nome ou macro de versão anterior.

## 5.0.0 — Adição

Comparação principal: 4.6.11.

Introduz um gerenciador de buffers com arenas de memória virtual e arquivo temporário mapeado, perfil configurável e diagnóstico. Integra o S.P.E.C.T.R.A e o Aurelius ao alocador e passa a criar os acumuladores de pares FX sob demanda.

Novos módulos. spectra_memory.h declara a interface; spectra_memory.inc implementa o alocador, as estatísticas e a persistência; spectra_memory_ui.inc acrescenta uma janela nativa com rolagem, foco e comandos de configuração. Os arquivos .inc são incluídos pelo principal, e não unidades de compilação independentes.

Armazenamento. O perfil oferece modo automático, somente RAM e preferência exclusiva por disco. A arena RAM usa VirtualAlloc; a de disco usa CreateFileMapping/MapViewOfFile sobre arquivo temporário. Na configuração inicial, os limites são 4 MiB em RAM e 32 MiB em disco, preservando uma folga declarada de 6 MiB. O orçamento efetivo depende dos recursos disponíveis. O arquivo mapeado também pode consumir cache RAM.

Proteções e diagnóstico. Introduz spm_malloc/calloc/realloc/free, canários, validação de cabeçalhos, páginas de guarda e possibilidade de marcar PCM recebido como somente leitura. VirtualLock é uma tentativa opcional em sistemas NT; a opção não garante residência física em Windows 9x. Estatísticas incluem uso, pico, falhas e liberações inválidas. Esses limites não abrangem código, dados estáticos, pilhas ou todos os recursos internos do Windows.

Ciclo de vida. Carrega e grava as seções Memoria e MemoriaUltimaExecucao em spectra_config.dat; alterações do perfil são destinadas à próxima inicialização. A opção --memory-safe desativa as arenas naquela execução. Inicialização, encerramento e tratamento de falha ao criar a janela principal passam pelo novo gerenciador.

Integração FX. g_fx_pairs deixa de reservar uma matriz estática completa de estruturas FxPair e passa a armazenar ponteiros: cada par é alocado quando usado e liberado no reset ou na remoção do nó. PCM e fila de transmissão passam pelo alocador monitorado. Isso reduz a reserva estática ociosa; não significa que o consumo total permaneça constante durante a observação.

Comparação. Mudam o principal, Aurelius e spectra_fx_win32.inc, além dos três módulos novos. spectra_fx_core.h permanece idêntico ao da 4.6.11. O pipeline passa a se identificar como 5.0.0-FX-EXPERIMENTAL; não há substituição do núcleo matemático FX nessa transição.

## 5.0.1 — Correção ou manutenção

Comparação principal: 5.0.0.

Corrige o falso diagnóstico de falha ao salvar o perfil de memória. A descarga do cache INI deixa de determinar o sucesso da gravação; o resultado passa a depender das escritas efetivas das chaves.

Falha localizada. spm_config_save e spm_record_status chamavam WritePrivateProfileStringA com argumentos nulos para descarregar o cache e tratavam um retorno falso como erro de gravação. A revisão mantém a chamada de descarga, mas retira esse retorno da variável de sucesso.

Efeito operacional. Quando as escritas das chaves têm sucesso, spm_config_save atualiza o perfil pendente e a interface pode informar o salvamento corretamente. Erros nas escritas reais continuam sendo verificados. A mudança não torna a aplicação do perfil imediata: permanece necessário reiniciar para alterar as arenas ativas.

Alcance executável. As alterações de comportamento concentram-se nas duas funções de persistência de spectra_memory.inc. O principal muda o identificador do pipeline para 5.0.1-FX-EXPERIMENTAL; o módulo de interface atualiza o título e comentários. Aurelius, spectra_fx_core.h, spectra_fx_win32.inc e spectra_memory.h são idênticos aos da 5.0.0.

## 5.1.2 — Revisão mista

Comparação principal: 5.0.1.

Amplia a compatibilidade do modo automático com fallback para o heap CRT, melhora o redimensionamento de buffers, exporta BMP por blocos e reforça a validação da fila FX. A janela de memória passa a acompanhar a seleção de idioma.

Fallback de alocação. spm_heap_allocate_locked centraliza as alocações CRT monitoradas. No modo automático, spm_malloc tenta a arena RAM, depois a de disco e, quando ambas não atendem, recorre ao heap CRT. Os modos exclusivos preservam a restrição às respectivas arenas. Portanto, no automático, o orçamento das arenas deixa de representar um teto rígido para todos os buffers monitorados.

Redimensionamento. spm_realloc usa realloc para os blocos CRT e tenta reutilizar ou ampliar um bloco de arena por meio de spm_arena_resize_locked. A rotina administra segmentos livres, canários, páginas de guarda, leitura protegida e contadores. A alternativa de alocar/copiar/liberar permanece quando o redimensionamento local não é possível.

BMP de Drift-Scan. exportar_varredura_bmp_direto deixa de montar toda a varredura em um grande bloco contíguo. Trabalha em segmentos de até 64 colunas e grava trechos das linhas BMP por posicionamento no arquivo. Valida tamanhos contra LONG_MAX, preenche lacunas da origem com zeros e remove o destino parcial em falhas de escrita. A transposição integral em RAM deixa de ser exigida; o custo de acesso ao disco permanece.

Transporte FX. fx_send_frame verifica a validade do quadro e do PCM, a coerência de g_fx_tx_pos/g_fx_tx_len e a existência do buffer. Também limita o pacote e faz o teste de capacidade restante da fila antes da soma, reduzindo a possibilidade de estados ou tamanhos inválidos serem enviados.

Idioma. spm_ui_language traduz controles, opções e diagnóstico; spm_language_changed atualiza a janela já aberta e reorganiza seu conteúdo. O botão global de idioma passa a chamar essa integração.

Identificação preservada. A pasta recebida se chama SPECTRA_5.1.2, mas o principal continua com o nome SPECTRA_5.0.1.c e o pipeline interno ainda declara 5.0.1-FX-EXPERIMENTAL. A entrada do histórico usa a versão da pasta e mantém esses arquivos originais sem renomear ou alterar seu conteúdo. Aurelius, spectra_fx_core.h e spectra_memory.h permanecem idênticos aos da 5.0.1.

## 5.2.1 — Atualização

Comparação principal: 5.1.2.

Atualiza a visualização de larga escala com seis níveis de detalhe, partículas alocadas dinamicamente e renderização em DIB de 32 bits. Introduz um modelo galáctico artístico guiado pelo brilho e pela cobertura dos CSVs, além de validar melhor os mosaicos.

Níveis de detalhe. O seletor oferece orçamentos de 9.000, 22.000, 48.000, 90.000, 150.000 e 240.000 partículas. O nível Baixo preserva a construção angular anterior. A sessão começa em Baixo; níveis elevados não são restaurados automaticamente na abertura seguinte. Quando a alocação não atende ao nível pedido, a construção tenta níveis inferiores.

Modelo novo. spectra_ls_galaxy_core.h fornece amostragem do mosaico, interpolação, máscara de cobertura e geração determinística de uma galáxia estilizada. O brilho e as regiões cobertas modulam densidade e intensidade; os quatro braços espirais e o bojo são hipóteses do modelo visual. A forma não constitui uma reconstrução física de distâncias da Via Láctea.

Memória e desenho. g_ls_modelo_3d passa de vetor estático a buffer dinâmico. A renderização usa lotes de projeção e mistura aditiva em um DIB BGRx, retirando a ordenação por profundidade e as chamadas GDI por partícula nesse caminho. Existe alternativa GDI reduzida quando a criação do DIB falha. A troca e a destruição dos objetos gráficos passam a restaurar o bitmap anterior do DC.

Importação de mosaicos. Conserva a máscara de células observadas, rejeita valores NaN ou de magnitude excessiva, reforça limites de dimensões/offsets e compara origem e passos angulares entre arquivos. Isso evita unir, como um único mosaico, seções com geometria incompatível. A projeção em lotes também passa a receber coordenadas por ponteiro linear.

Conjuntos de apoio. O pacote inclui CSVs sintéticos de uma galáxia 2D, dividida em quatro seções, e de cobertura parcial, com duas seções, além do gerador Python e instruções. Cada conjunto deve ser carregado separadamente. Esses arquivos não representam observações reais.

Organização e continuidade. Os fontes passam a estar em PARA_C_RADIO_TCC, com principal spectra.c e módulo aurelius.c. O header FX, o módulo FX Win32, os três arquivos de memória e o conteúdo executável de Aurelius permanecem iguais aos da 5.1.2. O pipeline interno ainda contém o identificador 5.0.1-FX-EXPERIMENTAL.

## 5.3.1 — Atualização

Comparação principal: 5.2.1.

Acrescenta um método 3D científico, separado do artístico, que usa distâncias heliocêntricas fornecidas no CSV. Inclui leitura de incertezas, amostragem por reservatório, referência dos eixos e correção da média de células com contagem saturada.

Método selecionável. O controle Artistica/Cientifica alterna a construção do modelo de larga escala. O modo artístico permanece disponível. O caminho científico exige metadados de coordenadas galácticas, unidade kpc e origem heliocêntrica, além de uma distância positiva por observação; CSVs antigos sem distância não produzem pontos nesse método.

Parser e geometria. spectra_ls_science_core.h acrescenta SpectraScienceRow, spectra_science_parse e spectra_science_xyz. O parser aceita seis colunas obrigatórias e uma incerteza opcional, rejeita lixo adicional e valida faixas. A transformação aplica X=d cos(b) cos(l), Y=d cos(b) sin(l) e Z=d sen(b), com o Sol na origem. A distância é recebida do arquivo, e não calculada a partir da intensidade.

Seleção e escala. ls_construir_modelo_cientifico lê os arquivos validados sob demanda, confere a coerência de longitude/latitude com a grade e usa um reservatório para limitar a quantidade de pontos. Cada linha aceita fornece no máximo um ponto. Uma mesma escala é aplicada aos três eixos para a exibição; não são acrescentados braços ou estrelas para preencher lacunas.

Proveniência e incerteza. O painel informa quantidade de observações, distância máxima, presença de sigma e incerteza relativa média. Distâncias do CSV não são verificadas pelo programa. Arquivos marcados DistanceKind=SYNTHETIC são apresentados como testes; o método científico, por si só, não transforma dados fabricados em medições astronômicas.

Correção do mosaico. Em ls_acumular_arquivo, a soma da célula passa a ser incrementada somente enquanto a contagem ainda pode crescer. Antes, o contador parava em 255, mas a soma continuava aumentando; isso distorcia a média em células com muitos registros.

Dados e continuidade. Acrescenta quatro seções científicas sintéticas, descritas no gerador como 132.000 pontos, e um conjunto de cinco referências dos eixos. O principal e o header científico são as mudanças de código da transição; Aurelius, FX, memória e spectra_ls_galaxy_core.h permanecem iguais aos da 5.2.1. O pipeline interno continua identificado como 5.0.1-FX-EXPERIMENTAL.

## 6.0.0 — Adição

Comparação principal: 5.3.1.

Adiciona captura científica de áudio demodulado AM/FM ao Drift-Scan. Exporta métricas temporais, espectro opcional, eventos, metadados do equipamento e resumo da sessão, sem atribuir ao PCM uma calibração de potência ou frequência de RF.

Dois módulos novos. spectra_audio_science.h implementa métricas e espectro de áudio sem depender da API Win32. spectra_audio_science.inc integra abertura, registro e fechamento dos produtos à captura de longo período. As opções de ativação e de espectro completo são gravadas na seção CapturaCientificaAudio de spectra_config.dat e ficam bloqueadas durante a aquisição.

Métricas de bloco. ca_measure calcula média, potência média quadrática em FS², RMS e pico em FS/dBFS, fração de clipping e indicadores de qualidade a partir de PCM de 16 bits normalizado. O módulo analisa o canal esquerdo ou mono e distingue silêncio e saturação. FS indica a escala completa do conversor de áudio, não uma unidade de fluxo astronômico.

Espectro opcional. ca_spectrum usa FFT radix-2 de 1.024 amostras, remoção de DC por quadro, janela Hann periódica e média de potência unilateral em 513 bins. O eixo é frequência de áudio; a soma dos bins aproxima a potência AC normalizada. Quadros incompletos menores que 1.024 amostras no final do bloco não entram nessa média. Exportar o espectro completo acrescenta processamento e gravação.

Produtos persistentes. A subpasta CIENCIA_AUDIO da sessão contém audio_observacoes.csv, eventos.csv e equipamento.ini; audio_espectros.csv é opcional e resumo_sessao.csv é produzido no encerramento quando houve registro. São armazenados identificação da sessão, blocos, tempos nominais e de detecção, posição nas amostras, estado de qualidade e coordenadas configuradas quando disponíveis.

Aquisição e falhas. ca_record_block registra blocos ativos; em intervalos fora da janela horária, conta a pausa e mantém a posição das amostras, com eventos PAUSE/RESUME. Falhas detectadas na criação ou gravação dos CSVs interrompem a captura para evitar a omissão silenciosa de registros. O fechamento é chamado também nos caminhos de cancelamento do início e de parada da sessão.

Interpretação física. O formato marca RF não verificada, relógio estimado e apontamento não verificado. Os valores de modulação, AGC e squelch podem ser declarados no perfil, mas não são validados automaticamente. Este módulo não estima distância, velocidade Doppler, densidade de fluxo, temperatura de antena ou potência RF a partir do áudio demodulado.

Comparação e testes fornecidos. O principal passa a declarar 6.0.0-AUDIO-SCIENCE e inclui o módulo de áudio; os oito módulos de código herdados permanecem iguais aos da 5.3.1. O pacote acrescenta um conjunto de áudio simulado de 800 Hz, dois blocos ativos e um pausado, identificado como teste com ambiente Win32 emulado. O arquivo de teste citado no LEIA_ME desse conjunto não veio no anexo e não foi inventado nesta integração.

## Arquivo de apoio separado

O conjunto SPECTRA_CSV_TESTES_CIENTIFICOS foi preservado em TESTES/CSV_CIENTIFICOS_COMPLEMENTARES. Não foi contado como versão do programa.

O portal passa a reunir 72 versões oficiais, uma variante, 142 fontes/módulos e 54 arquivos de apoio/teste. Os dados sintéticos estão identificados nas entradas e nos arquivos originais.

A análise não certifica compilação completa no Dev-C++ 4.9.9.2, execução em Windows legado ou validade de uma aquisição astronômica.