# Papiro Máximo 2.5

Plataforma PHP/MySQL para preparação de concursos militares, com banco oficial de questões, simulados, trilhas, caderno de erros, revisão espaçada, metas, planejamento adaptativo, métricas de estudo e Papiro IA.

## Destaques da versão 2.5

- **Plano diário adaptativo** criado a partir dos pontos fracos, revisões, trilha e metas do aluno.
- **Metas semanais** de horas, questões e simulados.
- **Mapa de domínio por matéria** com gráfico radar e identificação automática de fraquezas.
- **Simulados Personalizado, Inteligente e Prova**, com histórico, progresso persistente e relatório final.
- **Modo foco para resolver questões**, com cronômetro, atalhos A–E, confiança da resposta e navegação do simulado.
- **Caderno de erros avançado**, com causa do erro e agenda de revisão espaçada.
- **Revisão espaçada** em intervalos progressivos de 1, 3, 7, 15, 30 e 60 dias.
- **Papiro IA Tutor + Coach**, contextualizada com desempenho real, domínio, metas e revisões do aluno.
- **Qualidade do banco**, com denúncia de questões e painel administrativo de revisão.
- Dashboard com evolução, distribuição por matéria, precisão, metas, radar de domínio e plano do dia.
- Questões textuais exibidas como HTML/texto; imagens ficam apenas como apoio visual necessário.
- Mantido o acervo de 3.049 registros do material fornecido.
- Contas administrativas não participam do ranking.
- Tema claro/escuro e proteção de configurações locais via `.gitignore`.

## Instalação

Leia `PRIMEIROS-PASSOS.txt`. Em XAMPP, a pasta deve ficar em `C:\xampp\htdocs\plataforma-papiro`.

## Atualização da 2.4

Preserve `includes/config.local.php`, faça backup do banco e substitua apenas os arquivos da aplicação. **Não apague o banco MySQL.** As novas colunas/tabelas são criadas automaticamente no primeiro acesso.

## Segurança

Chaves de IA, senha do MySQL e outras configurações privadas devem ficar em `includes/config.local.php`. Esse arquivo está no `.gitignore` e não deve ser commitado.

Consulte `ALTERACOES-2.5.txt` para o changelog completo.
