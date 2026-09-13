# 📥 Pasta de simulados (PDF)

Jogue aqui os arquivos `.pdf` dos simulados — ou envie pela aba
**Admin → 📥 Importar PDFs**.

## Como funciona

1. A aba **Importar PDFs** lista os arquivos desta pasta.
2. Use **👁️ Ver texto** para conferir se o PDF é legível
   (PDF digitalizado/só-imagem não tem texto extraível — não funciona).
3. Clique em **🤖 Processar com IA**: ela identifica o **concurso e o ano**
   e extrai as questões no formato do banco
   (matéria, assunto, dificuldade, enunciado, A–E, gabarito, resolução).
   PDFs grandes continuam **sozinhos** até o fim (a tela mostra o progresso
   e quantas faltam: *"📊 Esperadas ~30 · faltam 5"*).
4. As questões entram como **rascunho** (invisíveis p/ alunos).
   Revise em **Admin → Questões**, corrija o que precisar e clique
   em **✅ Ativar** na importação.

## Simulados grandes (terminal, sem limite de tempo)

```bash
php tools/importar_pdf.php simulados/meu-simulado.pdf
php tools/importar_pdf.php simulados/meu-simulado.pdf --ativar --max-chunks=30
php tools/importar_pdf.php simulados/meu-simulado.pdf --ativar --prints
php tools/importar_pdf.php simulados/meu-simulado.pdf --inicio=6   (continua do trecho 7)
php tools/prints_pdf.php simulados/meu-simulado.pdf   (só os prints; precisa poppler/gs)
```

## Prints das páginas (automático)

Cada questão ganha o botão **📄 Ver print da página original** e o site
desenha a página sozinho no navegador do aluno (PDF.js embutido em
`assets/vendor/` — sem instalar nada, funciona até na InfinityFree).
Os PNGs via `pdftoppm`/`--prints` são opcionais (carregam mais rápido).

## Limitações

- PDFs protegidos por senha não abrem.
- Símbolos de fontes especiais (√, ∫, vetores) podem sair trocados —
  confira no rascunho e corrija antes de ativar.
- Se o PDF tiver gabarito no final, a IA usa ele; senão, ela resolve
  e deduz a alternativa correta (sempre revise!).
