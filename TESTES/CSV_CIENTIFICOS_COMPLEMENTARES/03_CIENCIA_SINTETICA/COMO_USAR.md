# Reconstrucao cientifica: CSVs SINTETICOS (nao sao observacoes)

Selecione os quatro arquivos `galaxia_science_01..04.csv` juntos. Na janela de
Interferometria de Larga Escala, habilite o mapa 3D e escolha `Cientifica (com distancia)`.
Cada linha e um objeto simulado, com longitude/latitude/d em kpc e incerteza
nominal artificial. A estrutura espiral aqui foi construida pelo gerador, nao
foi descoberta pelo SPECTRA. O 3D cientifico so reproduz a geometria desses
valores, nao a preenche com estrelas ou bracos imaginados.

Os antigos CSVs sem `distance_kpc` continuam validos para 2D e modo Artistico;
eles nao produzem pontos no modo Cientifico.

Colunas: x,y,galactic_lon_deg,galactic_lat_deg,intensity,distance_kpc,
distance_sigma_kpc (opcional). Cabeçalhos obrigatorios: # CoordinateSystem=GALACTIC_LB,
# DistanceUnit=kpc e # DistanceOrigin=HELIOCENTRIC; alem dos metadados originais.

Observacao: tratar uma distancia estimada por modelo como se fosse medicao
introduz vies cientifico. Catalogos reais precisam validar unidades, sistema
de coordenadas, metodo de inferencia de distancia, incerteza e selecao.
