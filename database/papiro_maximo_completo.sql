-- PAPIRO MÁXIMO 2.5 — BANCO COMPLETO (schema + seed)

-- ============================================================
-- PAPIRO MÁXIMO — Schema (MySQL/MariaDB)
-- Importe no phpMyAdmin OU rode o instalador install.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS papiro_maximo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE papiro_maximo;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  foco VARCHAR(20) NOT NULL DEFAULT 'EFOMM',
  role VARCHAR(10) NOT NULL DEFAULT 'aluno',
  ofensiva INT NOT NULL DEFAULT 0,
  ultima_atividade DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS app_meta (
  meta_key VARCHAR(80) PRIMARY KEY,
  meta_value VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS questoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL UNIQUE,
  concurso VARCHAR(20) NOT NULL DEFAULT 'Outro',
  ano INT NOT NULL DEFAULT 2024,
  numero INT NOT NULL DEFAULT 0,
  pagina INT NOT NULL DEFAULT 0,
  regiao VARCHAR(32) NOT NULL DEFAULT '',
  materia VARCHAR(50) NOT NULL DEFAULT 'Geral',
  assunto VARCHAR(100) NOT NULL DEFAULT '',
  dificuldade VARCHAR(10) NOT NULL DEFAULT 'Médio',
  enunciado TEXT NOT NULL,
  alt_a TEXT NOT NULL,
  alt_b TEXT NOT NULL,
  alt_c TEXT NOT NULL,
  alt_d TEXT NOT NULL,
  alt_e TEXT NOT NULL,
  gabarito TINYINT NOT NULL DEFAULT -1,
  gabarito_fonte VARCHAR(24) NOT NULL DEFAULT '',
  gabarito_confianca DECIMAL(5,4) NOT NULL DEFAULT 0,
  gabarito_validado_em DATETIME NULL,
  resolucao TEXT NOT NULL,
  origem VARCHAR(120) NOT NULL DEFAULT '',
  exibir_preview TINYINT NOT NULL DEFAULT 0,
  ativo TINYINT NOT NULL DEFAULT 1,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS importacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  arquivo VARCHAR(120) NOT NULL,
  concurso VARCHAR(20) NOT NULL DEFAULT 'Outro',
  ano INT NOT NULL DEFAULT 0,
  questoes INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'rascunho',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS questao_imagens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  questao_id INT NULL,
  arquivo VARCHAR(120) NOT NULL,
  legenda VARCHAR(150) NOT NULL DEFAULT '',
  origem VARCHAR(120) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS import_paginas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  arquivo VARCHAR(120) NOT NULL,
  pagina INT NOT NULL DEFAULT 1,
  arquivo_img VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS import_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  arquivo VARCHAR(120) NOT NULL,
  concurso VARCHAR(20) NOT NULL DEFAULT '',
  ano INT NOT NULL DEFAULT 0,
  inicio INT NOT NULL DEFAULT 0,
  total INT NOT NULL DEFAULT 0,
  esperadas INT NOT NULL DEFAULT 0,
  salvas INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'fila',
  erros TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS respostas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  questao_id INT NOT NULL,
  alternativa TINYINT NOT NULL,
  correta TINYINT NOT NULL DEFAULT 0,
  avaliavel TINYINT NOT NULL DEFAULT 1,
  tempo_seg INT NOT NULL DEFAULT 0,
  confianca VARCHAR(12) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS favoritos (
  user_id INT NOT NULL,
  questao_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, questao_id)
);

CREATE TABLE IF NOT EXISTS caderno_erros (
  user_id INT NOT NULL,
  questao_id INT NOT NULL,
  motivo VARCHAR(60) NOT NULL DEFAULT 'Errou a questão',
  anotacao TEXT NULL,
  revisada TINYINT NOT NULL DEFAULT 0,
  erro_tipo VARCHAR(24) NOT NULL DEFAULT '',
  proxima_revisao DATE NULL,
  revisoes INT NOT NULL DEFAULT 0,
  ultima_revisao DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, questao_id)
);

CREATE TABLE IF NOT EXISTS comentarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  questao_id INT NOT NULL,
  texto TEXT NOT NULL,
  aprovado TINYINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS study_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  inicio DATETIME NOT NULL,
  fim DATETIME NULL,
  duracao_seg INT NOT NULL DEFAULT 0,
  tipo VARCHAR(30) NOT NULL DEFAULT 'livre',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS trilhas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(30) NOT NULL UNIQUE,
  nome VARCHAR(80) NOT NULL,
  icone VARCHAR(10) NOT NULL DEFAULT '🗺️',
  cor VARCHAR(7) NOT NULL DEFAULT '#1E5AA8',
  descricao TEXT NULL,
  ativo TINYINT NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS trilha_modulos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trilha_id INT NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  descricao TEXT NULL
);

CREATE TABLE IF NOT EXISTS trilha_progresso (
  user_id INT NOT NULL,
  modulo_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, modulo_id)
);

CREATE TABLE IF NOT EXISTS guia_artigos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(30) NOT NULL UNIQUE,
  titulo VARCHAR(120) NOT NULL,
  icone VARCHAR(10) NOT NULL DEFAULT '📖',
  tempo VARCHAR(20) NOT NULL DEFAULT '5 min',
  texto TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS grupos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  codigo VARCHAR(12) NOT NULL UNIQUE,
  descricao TEXT NULL,
  dono_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS grupo_membros (
  grupo_id INT NOT NULL,
  user_id INT NOT NULL,
  entrou_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (grupo_id, user_id)
);

CREATE TABLE IF NOT EXISTS videoaulas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(150) NOT NULL,
  url VARCHAR(255) NOT NULL DEFAULT '',
  concurso VARCHAR(20) NOT NULL DEFAULT 'Outro',
  materia VARCHAR(50) NOT NULL DEFAULT 'Geral',
  descricao TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ia_perguntas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  questao_id INT NULL,
  pergunta TEXT NOT NULL,
  resposta TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ============================================================
-- PAPIRO 2.5 — planejamento, revisão espaçada e simulados
-- ============================================================


CREATE TABLE IF NOT EXISTS metas_usuario (
  user_id INT PRIMARY KEY,
  horas_semana DECIMAL(5,1) NOT NULL DEFAULT 10,
  questoes_semana INT NOT NULL DEFAULT 150,
  simulados_semana INT NOT NULL DEFAULT 1,
  updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS plano_diario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  dia DATE NOT NULL,
  tipo VARCHAR(24) NOT NULL DEFAULT 'estudo',
  titulo VARCHAR(180) NOT NULL,
  descricao TEXT NOT NULL,
  link VARCHAR(255) NOT NULL DEFAULT '',
  ordem INT NOT NULL DEFAULT 0,
  concluido TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  UNIQUE KEY uq_plano_user_dia_ordem (user_id,dia,ordem)
);

CREATE TABLE IF NOT EXISTS simulados_execucoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  modo VARCHAR(24) NOT NULL DEFAULT 'personalizado',
  concurso VARCHAR(20) NOT NULL DEFAULT '',
  materia VARCHAR(50) NOT NULL DEFAULT '',
  qtd INT NOT NULL DEFAULT 0,
  ids_json LONGTEXT NOT NULL,
  config_json TEXT NOT NULL,
  inicio DATETIME NOT NULL,
  fim DATETIME NULL,
  respondidas INT NOT NULL DEFAULT 0,
  avaliadas INT NOT NULL DEFAULT 0,
  acertos INT NOT NULL DEFAULT 0,
  INDEX idx_sim_user_inicio (user_id,inicio)
);

CREATE TABLE IF NOT EXISTS simulado_respostas (
  execucao_id INT NOT NULL,
  questao_id INT NOT NULL,
  alternativa TINYINT NOT NULL,
  correta TINYINT NOT NULL DEFAULT 0,
  avaliavel TINYINT NOT NULL DEFAULT 1,
  tempo_seg INT NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  PRIMARY KEY (execucao_id,questao_id)
);

CREATE TABLE IF NOT EXISTS questao_denuncias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  questao_id INT NOT NULL,
  tipo VARCHAR(32) NOT NULL DEFAULT 'outro',
  detalhe TEXT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'aberta',
  created_at DATETIME NULL,
  INDEX idx_denuncias_status (status,created_at)
);
-- PAPIRO 2.5


-- Contas ADM iniciais (troque as senhas após o primeiro login)
INSERT IGNORE INTO users (nome,email,senha_hash,foco,role) VALUES
('Administrador Geral','admin@papiromaximo.local','$2y$12$LyofOO5R9m7HSxIVL9XSTujUbkWC.3qf4KVkhX8BsxE7UU46S4myi','EFOMM','admin'),
('Gestor Papiro','gestor@papiromaximo.local','$2y$12$0qqcydOIlvLeFAwUAyDayOU8qJk3eYWB4PKmb2IeKEF985Ye.wRNK','ITA','admin'),
('Suporte Papiro','suporte@papiromaximo.local','$2y$12$WssN2saGLmpZd5UDOrB.GOOw4NPv5OkAy4UTbXBptODq/GFDGh4We','AFA','admin');


-- PAPIRO MÁXIMO — seed gerado de data/questoes.js
-- Importe schema.sql antes deste arquivo (ou use papiro_maximo_completo.sql).
USE papiro_maximo;

INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('efomm-01','EFOMM',2024,'Matemática','Trigonometria','Médio','O valor de <b>sen 75° · cos 15° + cos 75° · sen 15°</b> é igual a:','1/2','√2/2','√3/2','1','0',3,'Observe que a expressão é da forma <b>sen A·cos B + cos A·sen B = sen(A+B)</b>.<br>Logo: sen 75°·cos 15° + cos 75°·sen 15° = sen(75°+15°) = <b>sen 90° = 1</b>.<br><b>Gabarito: letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('efomm-02','EFOMM',2023,'Matemática','Determinantes','Médio','Seja A uma matriz quadrada de ordem 3 tal que <b>det(A) = 4</b>. O valor de <b>det(2A)</b> é:','8','12','16','24','32',4,'Propriedade: <b>det(k·A) = kⁿ · det(A)</b>, onde n é a ordem.<br>det(2A) = 2³ · 4 = 8 · 4 = <b>32</b>.<br><b>Gabarito: letra E.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('efomm-03','EFOMM',2024,'Física','Lançamento oblíquo','Difícil','Um projétil é lançado do solo com velocidade inicial de <b>20 m/s</b>, formando <b>30°</b> com a horizontal. Despreze a resistência do ar e adote <b>g = 10 m/s²</b>. O alcance horizontal máximo é, em metros:','10√3','20√3','20','30','40',1,'Alcance: <b>A = v₀² · sen(2θ) / g</b>.<br>A = 20² · sen 60° / 10 = 400 · (√3/2) / 10 = 200√3/10 = <b>20√3 m</b>.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('efomm-04','EFOMM',2023,'Física','Eletrodinâmica','Médio','Um resistor de <b>20 Ω</b> é percorrido por uma corrente de <b>3 A</b>. A potência dissipada por ele, em watts, é:','60','120','150','180','240',3,'Potência: <b>P = R · i²</b>.<br>P = 20 · 3² = 20 · 9 = <b>180 W</b>.<br><b>Gabarito: letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('efomm-05','EFOMM',2024,'Português','Interpretação de texto','Fácil','Assinale a alternativa em que a concordância verbal está de acordo com a norma-padrão:','Haviam muitos candidatos na sala.','Existe questões difíceis na prova.','Fazem dois anos que estudo para a EFOMM.','Houve mudanças no edital deste ano.','Ocorreu imprevistos durante o exame.',3,'O verbo <b>haver</b> (sentido de existir) e <b>fazer</b> (tempo decorrido) são impessoais: ficam no singular.<br>• \'Haviam\' → errado (correto: Havia). • \'Existe questões\' → errado (Existem). • \'Fazem\' → errado (Faz). • \'Ocorreu imprevistos\' → errado (Ocorreram).<br><b>Correta: \'Houve mudanças no edital\' — letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('efomm-06','EFOMM',2024,'Inglês','Reading comprehension','Médio','<i>\'Seafarers must comply with safety drills before departure, as negligence may jeopardize the entire crew.\'</i><br>The word <b>\'jeopardize\'</b> can be replaced, without change of meaning, by:','protect','endanger','ignore','delay','improve',1,'<b>Jeopardize = to put in danger = endanger</b> (pôr em risco).<br>Contexto: negligência pode colocar toda a tripulação em risco.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('efomm-07','EFOMM',2022,'Matemática','Geometria analítica','Médio','A distância entre o ponto <b>P(3, 4)</b> e a reta <b>3x + 4y − 10 = 0</b> é:','1','2','3','4','5',2,'Distância ponto-reta: <b>d = |ax₀ + by₀ + c| / √(a²+b²)</b>.<br>d = |3·3 + 4·4 − 10| / √(9+16) = |9+16−10|/5 = 15/5 = <b>3</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('efomm-08','EFOMM',2022,'Física','Hidrostática','Médio','Um navio desloca <b>5.000 m³</b> de água do mar (densidade <b>1.025 kg/m³</b>). Adote <b>g = 10 m/s²</b>. O empuxo sobre o navio, em newtons, é:','5,125 × 10⁶','5,125 × 10⁷','5,0 × 10⁶','5,0 × 10⁷','1,025 × 10⁷',1,'Empuxo: <b>E = ρ · V · g</b>.<br>E = 1025 · 5000 · 10 = 1025 · 5×10⁴ = 5,125×10⁷ N.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('epcar-01','EPCAR',2024,'Matemática','Equação do 2º grau','Fácil','A soma das raízes da equação <b>x² − 7x + 12 = 0</b> é:','−7','−12','5','7','12',3,'Por Girard: soma = <b>−b/a</b> = −(−7)/1 = <b>7</b>. (Raízes: 3 e 4.)<br><b>Gabarito: letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('epcar-02','EPCAR',2023,'Matemática','Geometria plana','Médio','Um triângulo retângulo tem catetos <b>6 cm</b> e <b>8 cm</b>. A medida da hipotenusa, em cm, é:','9','10','12','14','√28',1,'Pitágoras: h² = 6² + 8² = 36 + 64 = 100 → h = <b>10 cm</b>.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('epcar-03','EPCAR',2024,'Português','Morfologia','Fácil','Na frase <i>\'Os cadetes estudam com dedicação\'</i>, a palavra <b>\'com\'</b> é:','conjunção','preposição','advérbio','pronome','interjeição',1,'<b>Com</b> liga \'estudam\' a \'dedicação\', estabelecendo relação de modo — é <b>preposição</b>.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('epcar-04','EPCAR',2024,'Inglês','Grammar — Simple Past','Fácil','Choose the correct sentence:','She go to school yesterday.','She goed to school yesterday.','She went to school yesterday.','She goes to school yesterday.','She going to school yesterday.',2,'<b>Go</b> é irregular: go → <b>went</b> (past) → gone (participle). Com \'yesterday\' usa-se o simple past.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('epcar-05','EPCAR',2024,'Matemática','Porcentagem','Fácil','Uma loja oferece <b>20%</b> de desconto em um produto de <b>R$ 250,00</b>. O preço final é:','R$ 200,00','R$ 205,00','R$ 210,00','R$ 225,00','R$ 230,00',0,'Desconto: 20% de 250 = 0,20 × 250 = 50. Preço final: 250 − 50 = <b>R$ 200,00</b>.<br><b>Gabarito: letra A.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('epcar-06','EPCAR',2023,'Matemática','Radiciação','Médio','Simplificando <b>√50 + √18 − √8</b>, obtém-se:','4√2','5√2','6√2','6','2√6',2,'√50 = 5√2; √18 = 3√2; √8 = 2√2.<br>5√2 + 3√2 − 2√2 = <b>6√2</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('epcar-07','EPCAR',2023,'Português','Sintaxe','Médio','Em <i>\'O instrutor entregou as provas aos alunos\'</i>, a função sintática de <b>\'as provas\'</b> é:','sujeito','objeto direto','objeto indireto','adjunto adnominal','aposto',1,'\'Entregou\' é transitivo direto e indireto (entregar algo <b>a alguém</b>). \'As provas\' = o que foi entregue → <b>objeto direto</b>; \'aos alunos\' → objeto indireto.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('epcar-08','EPCAR',2022,'Inglês','Vocabulary','Fácil','The opposite of <b>\'brave\'</b> is:','courageous','fearless','cowardly','strong','bold',2,'Brave = corajoso. Opostos: <b>cowardly</b> (covarde). As demais são sinônimos.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('eear-01','EEAR',2024,'Português','Concordância nominal','Médio','Assinale a frase em que a concordância nominal está correta:','É proibido entrada de estranhos.','É proibida a entrada de estranhos.','Os candidatos mesmo assinaram a lista.','Seguem anexo os documentos.','Haviam bastantes pessoas na fila.',1,'Com artigo definido, \'proibido\' varia: <b>\'É proibida a entrada\'</b>. Sem artigo, fica invariável (\'É proibido entrada\'). \'Mesmo\' como adjunto varia (eles mesmos); \'anexo\' como adjetivo varia (anexos); \'haver\' existencial é impessoal (Havia).<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('eear-02','EEAR',2024,'Inglês','Reading','Médio','<i>\'The sergeant ordered the airmen to double-check the equipment before takeoff.\'</i> According to the sentence, the airmen were told to:','ignore the equipment','verify the equipment twice','replace the equipment','hide the equipment','sell the equipment',1,'<b>Double-check</b> = conferir duas vezes / verificar com atenção.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('eear-03','EEAR',2023,'Matemática','Progressões','Médio','Numa PA, o primeiro termo é <b>3</b> e a razão é <b>4</b>. O 10º termo é:','35','37','39','40','43',2,'Termo geral: aₙ = a₁ + (n−1)·r.<br>a₁₀ = 3 + 9·4 = 3 + 36 = <b>39</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('eear-04','EEAR',2023,'Física','MRU','Fácil','Uma aeronave voa a <b>900 km/h</b> em MRU. Em <b>20 minutos</b>, a distância percorrida, em km, é:','150','200','300','450','600',2,'20 min = 1/3 h. d = v·t = 900 × (1/3) = <b>300 km</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('eear-05','EEAR',2023,'Português','Crase','Médio','Assinale a alternativa em que o uso da crase está correto:','Chegaremos à noite.','Ele foi à pé para a base.','Estudo à distância há anos.','O relatório foi entregue à oficiais.','A prova começa à uma hora.',0,'Locução adverbial feminina de tempo: <b>\'à noite\'</b> (crase correta). \'A pé\' (palavra masculina) não leva crase; antes de verbo (\'distância\' aqui é verbo? não — mas \'estudo a distância\' sem crase por ser locução com palavra repetida/não determinada... regra prática: não há crase antes de \'distância\' indeterminada); antes de palavra masculina plural sem \'as\' não há crase; antes de hora determinada usa-se crase apenas com artigo: \'à uma hora\' → correto seria \'a uma hora\' (antes de numeral \'uma\' não há crase, salvo \'às uma hora\' em casos específicos).<br><b>Gabarito: letra A.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('eear-06','EEAR',2023,'Inglês','Modal verbs','Fácil','<i>\'Airmen ___ follow the chain of command at all times.\'</i> Choose the modal that expresses obligation:','might','could','may','must','would',3,'Obrigação forte: <b>must</b>. (Might/could/may = possibilidade; would = condicional.)<br><b>Gabarito: letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('eear-07','EEAR',2024,'Matemática','Trigonometria','Médio','Se <b>sen x = 3/5</b> e x pertence ao <b>1º quadrante</b>, então <b>cos x</b> vale:','−4/5','−3/5','3/4','4/5','5/4',3,'Relação fundamental: sen²x + cos²x = 1 → cos²x = 1 − 9/25 = 16/25 → cos x = ±4/5. No 1º quadrante, cosseno é positivo: <b>4/5</b>.<br><b>Gabarito: letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('eear-08','EEAR',2024,'Física','Calorimetria','Médio','Para aquecer <b>500 g</b> de água de <b>20 °C</b> até <b>60 °C</b>, sendo o calor específico <b>1 cal/g·°C</b>, a quantidade de calor necessária, em kcal, é:','10','15','20','25','40',2,'Q = m·c·ΔT = 500 · 1 · (60−20) = 500 · 40 = 20.000 cal = <b>20 kcal</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('cn-01','CN',2024,'Matemática','Produtos notáveis','Fácil','O valor de <b>(x + 3)² − (x − 3)²</b> é:','0','6x','12x','18','x² + 9',2,'(x+3)² = x²+6x+9; (x−3)² = x²−6x+9. Subtraindo: 6x−(−6x) = <b>12x</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('cn-02','CN',2024,'Matemática','Geometria plana','Médio','A soma dos ângulos internos de um <b>octógono regular</b> mede:','720°','900°','1080°','1260°','1440°',2,'S = (n−2)·180° = (8−2)·180° = 6·180° = <b>1080°</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('cn-03','CN',2024,'Português','Interpretação','Fácil','Em <i>\'O mar, calmo e imenso, convidava os marinheiros à aventura\'</i>, a expressão destacada por vírgulas exerce a função de:','vocativo','aposto','adjunto adverbial','sujeito','predicativo',1,'\'Calmo e imenso\' explica/caracteriza \'o mar\' — termo explicativo = <b>aposto</b>.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('cn-04','CN',2024,'Inglês','Reading','Fácil','<i>\'Brazil\'s Navy protects over 7,000 km of coastline.\'</i> The sentence is in the:','simple past','present perfect','simple present','future','past continuous',2,'\'Protects\' (verbo + s, 3ª pessoa) indica hábito/fato → <b>simple present</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('cn-05','CN',2023,'Matemática','Sistemas lineares','Médio','A solução do sistema <b>{ x + y = 10; x − y = 4 }</b> é:','x=3, y=7','x=7, y=3','x=5, y=5','x=6, y=2','x=4, y=6',1,'Somando as equações: 2x = 14 → x = 7. Substituindo: 7 + y = 10 → y = 3.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('cn-06','CN',2023,'Ciências','Física — Densidade','Fácil','Um bloco de <b>200 g</b> ocupa um volume de <b>50 cm³</b>. Sua densidade, em g/cm³, é:','2','3','4','5','10',2,'d = m/V = 200/50 = <b>4 g/cm³</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('cn-07','CN',2023,'Matemática','MMC e MDC','Médio','O MMC entre <b>12, 18 e 30</b> é:','60','90','120','180','360',3,'12 = 2²·3; 18 = 2·3²; 30 = 2·3·5. MMC = 2²·3²·5 = 4·9·5 = <b>180</b>.<br><b>Gabarito: letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('cn-08','CN',2022,'Português','Pontuação','Fácil','Assinale a frase pontuada corretamente:','Os alunos, estudaram muito para a prova.','Os alunos estudaram muito, para a prova.','Os alunos estudaram muito para a prova.','Os alunos, estudaram, muito para a prova.','Os, alunos estudaram muito para a prova.',2,'Não se separa sujeito do verbo nem verbo de seu complemento por vírgula. Frase direta, sem adjuntos deslocados → <b>sem vírgulas</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('ita-01','ITA',2024,'Matemática','Números complexos','Difícil','Se <b>z = 1 + i</b>, então <b>z¹⁰</b> é igual a:','32i','−32i','32','−32','16 + 16i',0,'Forma polar: z = √2·(cos 45° + i·sen 45°). Por De Moivre: z¹⁰ = (√2)¹⁰·(cos 450° + i·sen 450°) = 32·(cos 90° + i·sen 90°) = 32·(0 + i) = <b>32i</b>.<br><b>Gabarito: letra A.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('ita-02','ITA',2023,'Matemática','Polinômios','Difícil','O resto da divisão de <b>P(x) = x³ − 2x² + x − 1</b> por <b>(x − 1)</b> é:','−2','−1','0','1','2',1,'Teorema do resto: R = P(1) = 1 − 2 + 1 − 1 = <b>−1</b>.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('ita-03','ITA',2024,'Física','Dinâmica','Difícil','Um bloco de <b>2 kg</b> sobre plano horizontal sem atrito é puxado por força de <b>10 N</b> que forma <b>60°</b> com a horizontal. A aceleração horizontal do bloco, em m/s², é:','1,0','2,0','2,5','5,0','8,7',2,'Componente horizontal: Fx = F·cos 60° = 10 · 0,5 = 5 N. 2ª lei: a = F/m = 5/2 = <b>2,5 m/s²</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('ita-04','ITA',2023,'Física','Óptica','Médio','Um objeto está a <b>30 cm</b> de um espelho plano. A distância entre o objeto e sua imagem é:','15 cm','30 cm','45 cm','60 cm','90 cm',3,'No espelho plano, a imagem é simétrica: forma-se a 30 cm \'atrás\' do espelho. Distância objeto–imagem = 30 + 30 = <b>60 cm</b>.<br><b>Gabarito: letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('ita-05','ITA',2024,'Química','Estequiometria','Médio','Na combustão completa do metano (<b>CH₄ + 2O₂ → CO₂ + 2H₂O</b>), a massa de CO₂ produzida a partir de <b>32 g</b> de CH₄ é: (massas molares: CH₄ = 16 g/mol; CO₂ = 44 g/mol)','22 g','32 g','44 g','66 g','88 g',4,'32 g de CH₄ = 2 mol. Proporção 1:1 com CO₂ → 2 mol de CO₂ = 2 × 44 = <b>88 g</b>.<br><b>Gabarito: letra E.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('ita-06','ITA',2024,'Português','Figuras de linguagem','Médio','Em <i>\'Ele tem um coração de pedra\'</i>, a figura de linguagem presente é:','hipérbole','eufemismo','ironia','metáfora','aliteração',3,'Comparação implícita (coração = pedra, pela dureza) sem conectivo → <b>metáfora</b>.<br><b>Gabarito: letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('ita-07','ITA',2024,'Inglês','Reading — inference','Difícil','<i>\'Although the experiment yielded inconclusive results, the data hinted at a correlation that warrants further investigation.\'</i> It can be inferred that:','the experiment was a complete failure','no correlation exists','more research is needed','the data was fabricated','the hypothesis was confirmed',2,'\'Warrants further investigation\' = merece investigação adicional → <b>more research is needed</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('ita-08','ITA',2024,'Matemática','Análise combinatória','Difícil','De quantas formas <b>5 pessoas</b> podem se sentar em fila se <b>duas delas (A e B) devem ficar juntas</b>?','24','48','60','72','120',1,'Trate AB como um bloco: 4 \'objetos\' → 4! = 24. Dentro do bloco, A e B permutam: 2! = 2. Total: 24 × 2 = <b>48</b>.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('esa-01','ESA',2024,'Matemática','Função afim','Fácil','Se <b>f(x) = 3x − 5</b>, então <b>f(4)</b> vale:','5','6','7','9','12',2,'f(4) = 3·4 − 5 = 12 − 5 = <b>7</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('esa-02','ESA',2024,'Português','Classes de palavras','Fácil','Em <i>\'O soldado brasileiro é disciplinado\'</i>, a palavra <b>\'brasileiro\'</b> é:','substantivo','adjetivo','advérbio','verbo','pronome',1,'\'Brasileiro\' caracteriza o substantivo \'soldado\' → <b>adjetivo</b>.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('espcex-01','EsPCEx',2024,'Matemática','Logaritmos','Médio','O valor de <b>log₂ 32</b> é:','4','5','6','8','16',1,'log₂ 32 = x → 2ˣ = 32 = 2⁵ → x = <b>5</b>.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('espcex-02','EsPCEx',2024,'Física','MUV','Médio','Um móvel parte do repouso com aceleração de <b>2 m/s²</b>. Após <b>5 s</b>, sua velocidade, em m/s, é:','5','7','10','12','25',2,'v = v₀ + at = 0 + 2·5 = <b>10 m/s</b>.<br><b>Gabarito: letra C.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('afa-01','AFA',2024,'Matemática','Matrizes','Médio','Se <b>A = [[1,2],[3,4]]</b>, então <b>det(A)</b> vale:','−2','−1','2','5','10',0,'det = 1·4 − 2·3 = 4 − 6 = <b>−2</b>.<br><b>Gabarito: letra A.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('afa-02','AFA',2024,'Inglês','Grammar','Médio','<i>\'The pilot ___ flying for ten years.\'</i> Complete with the present perfect continuous:','has been','have been','is','was','will be',0,'3ª pessoa singular → <b>has been flying</b>.<br><b>Gabarito: letra A.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('ime-01','IME',2024,'Matemática','Binômio de Newton','Difícil','O coeficiente de <b>x²</b> no desenvolvimento de <b>(x + 2)⁴</b> é:','8','12','16','24','32',3,'Termo geral: C(4,k)·x^(4−k)·2ᵏ. Para x²: k=2 → C(4,2)·2² = 6·4 = <b>24</b>.<br><b>Gabarito: letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('ime-02','IME',2023,'Química','Soluções','Médio','A concentração, em g/L, de uma solução com <b>20 g</b> de soluto em <b>500 mL</b> é:','10','20','30','40','50',3,'500 mL = 0,5 L. C = 20/0,5 = <b>40 g/L</b>.<br><b>Gabarito: letra D.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('cfn-01','CFN',2024,'Matemática','Regra de três','Fácil','Se <b>4 marinheiros</b> pintam um compartimento em <b>6 horas</b>, em quanto tempo <b>8 marinheiros</b> (mesmo ritmo) pintam o mesmo compartimento?','2 h','3 h','4 h','8 h','12 h',1,'Grandezas inversamente proporcionais: dobrou a equipe → cai pela metade o tempo: <b>3 h</b>.<br><b>Gabarito: letra B.</b>');
INSERT INTO questoes (slug, concurso, ano, materia, assunto, dificuldade, enunciado, alt_a, alt_b, alt_c, alt_d, alt_e, gabarito, resolucao) VALUES ('cfn-02','CFN',2024,'Português','Ortografia','Fácil','Assinale a palavra grafada corretamente:','excessão','exceção','eccessão','exceçâo','execção',1,'O correto é <b>exceção</b> (com \'ç\').<br><b>Gabarito: letra B.</b>');
INSERT INTO trilhas (slug, nome, icone, cor) VALUES ('efomm','EFOMM','⚓','#1E5AA8');
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (1,'Matemática Básica',1);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (1,'Funções & Trigonometria',2);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (1,'Geometria',3);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (1,'Física I — Mecânica',4);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (1,'Física II — Eletricidade',5);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (1,'Português',6);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (1,'Inglês',7);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (1,'Simulados finais',8);
INSERT INTO trilhas (slug, nome, icone, cor) VALUES ('epcar','EPCAR','✈️','#0E7C5B');
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (2,'Aritmética',1);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (2,'Álgebra',2);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (2,'Geometria Plana',3);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (2,'Português Total',4);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (2,'Inglês Total',5);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (2,'Revisão + Simulados',6);
INSERT INTO trilhas (slug, nome, icone, cor) VALUES ('eear','EEAR','🛩️','#7B3FA4');
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (3,'Português (Gramática)',1);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (3,'Inglês',2);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (3,'Matemática',3);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (3,'Física',4);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (3,'Baterias de questões',5);
INSERT INTO trilhas (slug, nome, icone, cor) VALUES ('cn','Colégio Naval','🧭','#C25700');
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (4,'Matemática Fundamental',1);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (4,'Álgebra',2);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (4,'Geometria',3);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (4,'Português',4);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (4,'Inglês',5);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (4,'Ciências',6);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (4,'Simulados CN',7);
INSERT INTO trilhas (slug, nome, icone, cor) VALUES ('ita','ITA','🚀','#B00020');
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (5,'Matemática Avançada',1);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (5,'Física Avançada',2);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (5,'Química',3);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (5,'Português & Redação',4);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (5,'Inglês Avançado',5);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (5,'Provas antigas comentadas',6);
INSERT INTO trilhas (slug, nome, icone, cor) VALUES ('base','Base Forte em Matemática','📐','#8A6D00');
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (6,'Operações & Frações',1);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (6,'Potências & Raízes',2);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (6,'Equações',3);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (6,'Porcentagem & Proporção',4);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (6,'Geometria básica',5);
INSERT INTO trilha_modulos (trilha_id, titulo, ordem) VALUES (6,'Funções',6);
INSERT INTO guia_artigos (slug, titulo, icone, tempo, texto) VALUES ('mat','Como estudar Matemática','📐','8 min','<b>1. Teoria mínima, questão máxima.</b> Para cada tópico, estude o conceito em 20–30 min e resolva 15–25 questões.<br><b>2. Caderno de erros.</b> Toda questão errada entra no caderno com o motivo: conceito, conta ou interpretação.<br><b>3. Revisão espaçada.</b> Revise em 24h, 7 dias e 30 dias.<br><b>4. Base forte.</b> 80% dos erros em concursos militares vêm de frações, potências e equações — domine a trilha Base Forte antes de avançar.');
INSERT INTO guia_artigos (slug, titulo, icone, tempo, texto) VALUES ('fis','Como estudar Física','⚛️','7 min','<b>1. Entenda antes de decorar.</b> Física cobra aplicação de leis (Newton, conservação de energia). Desenhe o problema.<br><b>2. Liste as fórmulas por assunto</b> e refaça as deduções principais.<br><b>3. Questões por nível:</b> comece pela EEAR/EPCAR e suba para EFOMM/ITA.<br><b>4. Unidades importam:</b> confira sempre m/s × km/h, cal × joule.');
INSERT INTO guia_artigos (slug, titulo, icone, tempo, texto) VALUES ('rev','Revisão','🔁','5 min','Use o ciclo <b>24h → 7d → 30d</b>. Revisão não é reler: é <b>refazer questões</b> sem olhar a resolução. Marque no app as questões para revisar e use o modo Revisão do Caderno de Erros.');
INSERT INTO guia_artigos (slug, titulo, icone, tempo, texto) VALUES ('erros','Caderno de erros','📓','6 min','Para cada erro registre: <b>(a)</b> o que a questão pedia, <b>(b)</b> por que você errou, <b>(c)</b> a forma correta em 2 linhas. Releia o caderno toda semana antes do simulado. No Papiro Máximo, erros vão automaticamente para o Caderno.');
INSERT INTO guia_artigos (slug, titulo, icone, tempo, texto) VALUES ('sim','Simulados','⏱️','6 min','<b>1 por semana</b>, no horário da prova, sem pausa e sem celular. Corrija no mesmo dia e jogue os erros no caderno. Acompanhe a taxa de acerto no Início — meta: <b>70%+</b> no seu concurso-alvo.');
INSERT INTO guia_artigos (slug, titulo, icone, tempo, texto) VALUES ('recall','Active Recall','🧠','5 min','Feche o material e tente explicar o tópico em voz alta. Se travar, é ali que está a lacuna. Em questões: cubra o gabarito e force a resposta antes de confirmar. O esforço de lembrar é o que fixa.');
INSERT INTO guia_artigos (slug, titulo, icone, tempo, texto) VALUES ('org','Organização semanal','🗓️','6 min','Modelo sugerido (2h/dia): <b>Seg/Qua/Sex</b> — Matemática + questões; <b>Ter/Qui</b> — Física + Português/Inglês alternados; <b>Sáb</b> — simulado + correção; <b>Dom</b> — revisão leve + caderno de erros. Registre as horas no timer do Início para manter a ofensiva.');
INSERT INTO videoaulas (titulo, url, concurso, materia, descricao) VALUES
('Como começar na EFOMM: plano de 90 dias','https://www.youtube.com/','EFOMM','Geral','Visão geral da prova, pesos e cronograma sugerido.'),
('EPCAR Matemática: os 10 temas que mais caem','https://www.youtube.com/','EPCAR','Matemática','Análise dos assuntos campeões de cobrança.'),
('Física para EEAR do zero','https://www.youtube.com/','EEAR','Física','Cinemática e dinâmica com questões comentadas.'),
('Colégio Naval: geometria plana essencial','https://www.youtube.com/','CN','Matemática','Teoremas e truques de construção.'),
('ITA: como estudar por provas antigas','https://www.youtube.com/','ITA','Geral','Método de engenharia reversa da banca.');
