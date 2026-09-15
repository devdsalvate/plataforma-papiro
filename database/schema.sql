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
