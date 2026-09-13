# 📱 Papiro Máximo — Estratégia Mobile

O app é **mobile-first**: todo o PHP gera HTML responsivo + PWA instalável
(`manifest.json` + `sw.js`). No celular, o usuário pode **"Adicionar à tela
inicial"** e usar como app (ícone, splash, offline parcial de assets).

## API interna (para um futuro app nativo / Flutter / React Native)

`POST api.php` (sessão + CSRF hoje; evoluir para token Bearer):

| action          | parâmetros                    | retorno                    |
|-----------------|-------------------------------|----------------------------|
| responder       | qid, alt (0-4), tempo (seg)   | correta, gabarito, letra   |
| favorito        | qid                           | fav (bool)                 |
| comentario      | qid, texto                    | nome                       |
| sessao_inicio   | —                             | id                         |
| sessao_fim      | id, seg                       | seg                        |
| trilha_toggle   | modulo                        | done, pct                  |
| caderno_salvar  | qid, anotacao                 | ok                         |
| caderno_revisada| qid, v                        | ok                         |
| caderno_remover | qid                           | ok                         |
| ia              | pergunta, questao_id?         | resposta (HTML), demo      |

Para o app nativo, o próximo passo é criar `api/token.php` (login → token)
e aceitar `Authorization: Bearer` no `api.php`.
