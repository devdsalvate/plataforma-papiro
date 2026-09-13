// Converte data/questoes.js -> database/seed.php + database/seed.sql
const fs = require('fs');
const path = require('path');
const src = fs.readFileSync(path.join(__dirname, 'questoes.js'), 'utf8');
const window = {};
eval(src);
const Q = window.PAPIRO_QUESTOES;
const T = window.PAPIRO_TRILHAS;
const G = window.PAPIRO_GUIA;

const escPhp = s => String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
const escSql = s => String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'");

// ---- seed.php ----
let php = "<?php\n// Gerado automaticamente de data/questoes.js — não editar à mão.\n";
php += "return [\n'questoes' => [\n";
for (const q of Q) {
  php += `['slug'=>'${escPhp(q.id)}','concurso'=>'${escPhp(q.concurso)}','ano'=>${q.ano},'materia'=>'${escPhp(q.materia)}','assunto'=>'${escPhp(q.assunto)}','dificuldade'=>'${escPhp(q.dificuldade)}',\n`;
  php += `'enunciado'=>'${escPhp(q.enunciado)}',\n`;
  const alts = q.alternativas.map(a => `'${escPhp(a)}'`).join(',');
  php += `'alternativas'=>[${alts}],'gabarito'=>${q.gabarito},\n'resolucao'=>'${escPhp(q.resolucao)}'],\n`;
}
php += "],\n'trilhas' => [\n";
for (const t of T) {
  const mods = t.modulos.map(m => `'${escPhp(m)}'`).join(',');
  php += `['slug'=>'${escPhp(t.id)}','nome'=>'${escPhp(t.nome)}','icone'=>'${escPhp(t.icone)}','cor'=>'${escPhp(t.cor)}','modulos'=>[${mods}]],\n`;
}
php += "],\n'guia' => [\n";
for (const g of G) {
  php += `['slug'=>'${escPhp(g.id)}','titulo'=>'${escPhp(g.titulo)}','icone'=>'${escPhp(g.icone)}','tempo'=>'${escPhp(g.tempo)}','texto'=>'${escPhp(g.texto)}'],\n`;
}
php += "],\n];\n";
fs.writeFileSync(path.join(__dirname, '..', 'database', 'seed.php'), php);

// ---- seed.sql ----
let sql = "-- PAPIRO MÁXIMO — seed gerado de data/questoes.js\n-- Importe schema.sql antes deste arquivo.\n\n";
for (const q of Q) {
  const a = q.alternativas.map(escSql);
  sql += `INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('${escSql(q.id)}','${escSql(q.concurso)}',${q.ano},'${escSql(q.materia)}','${escSql(q.assunto)}','${escSql(q.dificuldade)}','${escSql(q.enunciado)}','${a[0]}','${a[1]}','${a[2]}','${a[3]}','${a[4]}',${q.gabarito},'${escSql(q.resolucao)}');\n`;
}
let tid = 0;
for (const t of T) {
  tid++;
  sql += `INSERT INTO trilhas (slug, nome, icone, cor) VALUES ('${escSql(t.id)}','${escSql(t.nome)}','${escSql(t.icone)}','${escSql(t.cor)}');\n`;
  t.modulos.forEach((m, i) => {
    sql += `INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (${tid},'${escSql(m)}',${i + 1});\n`;
  });
}
for (const g of G) {
  sql += `INSERT INTO guia_artigos (slug, titulo, icone, tempo, texto) VALUES ('${escSql(g.id)}','${escSql(g.titulo)}','${escSql(g.icone)}','${escSql(g.tempo)}','${escSql(g.texto)}');\n`;
}
sql += `INSERT INTO videoaulas (titulo, url, concurso, materia, descricao) VALUES
('Como começar na EFOMM: plano de 90 dias','https://www.youtube.com/','EFOMM','Geral','Visão geral da prova, pesos e cronograma sugerido.'),
('EPCAR Matemática: os 10 temas que mais caem','https://www.youtube.com/','EPCAR','Matemática','Análise dos assuntos campeões de cobrança.'),
('Física para EEAR do zero','https://www.youtube.com/','EEAR','Física','Cinemática e dinâmica com questões comentadas.'),
('Colégio Naval: geometria plana essencial','https://www.youtube.com/','CN','Matemática','Teoremas e truques de construção.'),
('ITA: como estudar por provas antigas','https://www.youtube.com/','ITA','Geral','Método de engenharia reversa da banca.');
`;
fs.writeFileSync(path.join(__dirname, '..', 'database', 'seed.sql'), sql);
console.log(`OK: ${Q.length} questões, ${T.length} trilhas, ${G.length} artigos.`);
