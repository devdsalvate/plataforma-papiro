# 📜 Papiro Máximo

Plataforma web de preparação para concursos e escolas militares, com foco principal em **EFOMM, EPCAR, EEAR, Colégio Naval e ITA**.

O objetivo do projeto é reunir, em um único ambiente, **banco de questões, trilhas de aprendizagem, controle de horas estudadas, ranking entre alunos, comentários, favoritos, caderno de erros, estatísticas de desempenho e resoluções assistidas por IA**.

A plataforma será construída com **HTML, CSS, JavaScript, PHP e MySQL/MariaDB**, com hospedagem inicial no **InfinityFree**.

---

# 🎯 Objetivo

O **Papiro Máximo** não será apenas um banco de questões.

A ideia é criar uma plataforma em que o estudante consiga:

- saber o que estudar;
- entender como estudar;
- organizar sua preparação por concurso;
- resolver questões;
- acompanhar seu desempenho;
- registrar horas de estudo;
- manter uma ofensiva de estudos;
- comparar sua constância com outros alunos;
- participar de grupos;
- revisar erros;
- favoritar questões;
- comentar e publicar resoluções;
- acessar videoaulas;
- receber explicações geradas por IA.

O foco inicial será em aproximadamente **30 usuários**, mantendo uma arquitetura simples, organizada e preparada para crescimento.

---

# 🪖 Concursos principais

Os cinco concursos prioritários da plataforma serão:

- ⚓ **EFOMM**
- ✈️ **EPCAR**
- ✈️ **EEAR**
- ⚓ **Colégio Naval**
- ⚙️ **ITA**

Esses concursos terão:

- maior quantidade de questões;
- trilhas próprias;
- filtros dedicados;
- conteúdos recomendados;
- estatísticas específicas;
- simulados futuramente.

Outros concursos também poderão existir:

- ESA
- EsPCEx
- AFA
- IME
- EEAM
- CFN
- outros concursos militares.

---

# 🧱 Tecnologias

## Front-end

- HTML5
- CSS3
- JavaScript

## Back-end

- PHP 8+

## Banco de dados

- MySQL / MariaDB
- PDO para acesso ao banco

## Autenticação

- PHP Sessions
- `password_hash()`
- `password_verify()`

## Inteligência Artificial

- Groq API
- Comunicação feita pelo PHP
- API Key protegida no servidor

## Hospedagem

- InfinityFree

## Desenvolvimento local

Recomendado:

- XAMPP
- Apache
- MySQL
- phpMyAdmin
- VS Code

---

# 🏗️ Arquitetura

```text
NAVEGADOR
│
├── HTML
├── CSS
└── JavaScript
      │
      │ fetch()
      ▼
PHP / API INTERNA
      │
      ├── autenticação
      ├── questões
      ├── comentários
      ├── favoritos
      ├── sessões de estudo
      ├── ranking
      ├── grupos
      ├── trilhas
      └── IA
      │
      ▼
MySQL / MariaDB
      │
      └── Groq API
