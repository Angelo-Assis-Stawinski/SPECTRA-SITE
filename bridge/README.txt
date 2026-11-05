PONTE DE TELEMETRIA

1. Copie a pasta bridge para a máquina intermediária (a que tem Internet).
2. Copie config.example.json para config.json e ajuste spectra_root, server_url e o token mostrado no Painel Admin.
3. Compartilhe a pasta do S.P.E.C.T.R.A da máquina antiga somente para leitura/escrita na LAN privada, sem encaminhamento de portas da Internet.
4. Execute sync_telemetry.py periodicamente (por exemplo a cada 5 minutos no Agendador de Tarefas). O script só envia algo quando o S.P.E.C.T.R.A gera uma nova geração ou um sync.request.
5. A agregação de larga escala, média histórica dos espectros, índice de arquivos e upload acontecem aqui, não no K6-2.

Servidor: PHP 8+ com PDO_SQLite. HTTPS é recomendado.
