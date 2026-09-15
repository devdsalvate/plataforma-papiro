<?php
declare(strict_types=1);

/** Conteúdo pedagógico detalhado das trilhas e do guia. */
function lc_module(string $titulo, string $fase, string $materia, string $objetivo, array $conteudos, array $pratica, string $meta, string $dominio, string $tempo): array {
    return compact('titulo','fase','materia','objetivo','conteudos','pratica','meta','dominio','tempo');
}

/** @return array<string,array<string,mixed>> */
function papiro_learning_trails(): array {
    $M = 'Matemática'; $F='Física'; $Q='Química'; $P='Português'; $I='Inglês';
    return [
      'base' => [
        'nome'=>'Base Forte em Matemática','icone'=>'∑','cor'=>'#1769aa',
        'descricao'=>'Para quem sente lacunas acumuladas. Começa na aritmética realmente necessária e termina com uma base capaz de sustentar álgebra, funções, geometria e trigonometria de concursos militares.',
        'modulos'=>[
          lc_module('Diagnóstico e rotina de cálculo','Fundação',$M,'Descobrir onde você erra e recuperar segurança operacional.',['ordem das operações','tabuada e cálculo mental','estimativa','divisibilidade','múltiplos e divisores'],['faça um diagnóstico de 30 questões sem calculadora','crie uma lista dos 5 tipos de erro mais frequentes','10 minutos de cálculo mental por sessão'],'60 questões + mapa de lacunas','≥ 85% em operações e divisibilidade','3–5 dias'),
          lc_module('Inteiros, sinais e módulo','Fundação',$M,'Eliminar erros de sinal que contaminam álgebra e física.',['reta numérica','comparação','operações com negativos','regra de sinais','valor absoluto'],['20 exercícios graduais','explique em voz alta por que (-3)·(-2)>0','refaça os erros 24h depois'],'45 questões','≥ 85% sem erro de sinal','2–3 dias'),
          lc_module('Frações sem medo','Fundação',$M,'Entender fração como número e dominar operações.',['equivalência','simplificação','MMC aplicado','soma/subtração','produto/divisão','fração de quantidade'],['represente frações em desenhos antes da conta','30 questões só de operações','10 problemas de texto com frações'],'70 questões','≥ 80% em problemas e ≥ 90% em operações','4–6 dias'),
          lc_module('Decimais, unidades e notação científica','Fundação',$M,'Conectar decimais, frações e medidas usadas em Física.',['conversão decimal↔fração','arredondamento','unidades','potências de 10','notação científica'],['faça uma folha de conversões','15 exercícios de unidade','20 de notação científica'],'50 questões','≥ 85%','3–4 dias'),
          lc_module('Razão, proporção e regra de três','Fundação',$M,'Modelar comparações e escalas sem decorar receitas.',['razão','proporção','grandezas direta/inversa','regra de três','escala'],['identifique primeiro as grandezas','20 problemas cotidianos','10 problemas com escala/mapa'],'55 questões','≥ 80% em problemas','3–5 dias'),
          lc_module('Porcentagem e variação','Fundação',$M,'Interpretar porcentagem como fator multiplicativo.',['porcentagem','aumentos/descontos','variação percentual','porcentagens sucessivas','juros simples básicos'],['resolva 20 sem regra de três','compare método fator x proporção','faça 2 baterias cronometradas'],'60 questões','≥ 85%','3–5 dias'),
          lc_module('Potências, raízes e propriedades','Fundação',$M,'Ganhar fluidez para álgebra, funções e geometria.',['expoentes inteiros','propriedades','raízes','racionalização básica','expoente fracionário'],['monte um resumo de propriedades com exemplos','40 exercícios mecânicos','20 exercícios mistos'],'70 questões','≥ 85%','4–6 dias'),
          lc_module('Linguagem algébrica','Álgebra',$M,'Transformar texto em expressão e manipular símbolos sem se perder.',['monômios/polinômios','distributiva','redução de termos','substituição','expressões'],['traduza 15 frases para álgebra','simplifique 30 expressões','verifique por substituição numérica'],'55 questões','≥ 85%','3–4 dias'),
          lc_module('Equações do 1º grau','Álgebra',$M,'Resolver e montar equações a partir de problemas.',['princípio da igualdade','equações','problemas','equações fracionárias simples'],['20 equações graduais','20 problemas','sempre confira a solução no enunciado'],'60 questões','≥ 85%','4 dias'),
          lc_module('Sistemas lineares','Álgebra',$M,'Escolher substituição ou eliminação e interpretar sistemas.',['sistemas 2×2','substituição','eliminação','problemas','interpretação gráfica'],['15 sistemas por método','15 problemas','esboce 5 sistemas como retas'],'50 questões','≥ 80%','3–4 dias'),
          lc_module('Produtos notáveis e fatoração','Álgebra',$M,'Reconhecer padrões para simplificar expressões rapidamente.',['quadrado da soma/diferença','diferença de quadrados','fator comum','agrupamento','trinômio'],['crie cartões de padrão↔fatoração','40 exercícios','10 simplificações de frações algébricas'],'65 questões','≥ 85%','4–5 dias'),
          lc_module('Equação do 2º grau','Álgebra',$M,'Dominar raízes, discriminante e relações de Viète.',['Bhaskara','discriminante','soma/produto das raízes','problemas','parábola inicial'],['30 equações','10 problemas','compare fatoração x fórmula'],'55 questões','≥ 85%','3–5 dias'),
          lc_module('Inequações e intervalos','Álgebra',$M,'Raciocinar com conjuntos-solução e sinais.',['1º e 2º grau','produto/quociente','intervalos','módulo básico'],['desenhe a reta real em todas as respostas','20 tabelas de sinais','15 inequações com módulo simples'],'55 questões','≥ 80%','4–5 dias'),
          lc_module('Geometria plana essencial','Geometria',$M,'Construir visão geométrica antes de fórmulas avançadas.',['ângulos','triângulos','semelhança','Pitágoras','quadriláteros','polígonos','circunferência','áreas'],['desenhe toda questão','prove 3 relações em vez de decorar','faça bateria por figura'],'90 questões','≥ 80% e conseguir explicar o desenho','7–10 dias'),
          lc_module('Geometria espacial e medidas','Geometria',$M,'Visualizar sólidos e controlar unidades.',['prismas','pirâmides','cilindro','cone','esfera','área/volume'],['monte sólidos em papel ou visualize em 3D','30 questões','faça uma tabela mínima de fórmulas derivadas'],'55 questões','≥ 80%','4–6 dias'),
          lc_module('Plano cartesiano e geometria analítica','Funções',$M,'Ligar álgebra a gráficos.',['coordenadas','distância','ponto médio','inclinação','equação da reta','circunferência básica'],['plote à mão','20 questões de reta','10 problemas geométricos no plano'],'55 questões','≥ 80%','4–6 dias'),
          lc_module('Funções do zero','Funções',$M,'Entender função como relação e leitura de gráfico.',['domínio/imagem','função afim','quadrática','gráficos','zeros','crescimento'],['faça gráficos à mão','ligue fórmula↔gráfico','30 questões interpretativas'],'70 questões','≥ 80%','6–8 dias'),
          lc_module('Exponencial e logaritmo','Funções',$M,'Usar potências para modelar crescimento e dominar logaritmos.',['função exponencial','definição de log','propriedades','equações','gráficos'],['derive propriedades a partir da definição','30 equações','20 gráficos/problemas'],'65 questões','≥ 80%','5–7 dias'),
          lc_module('Trigonometria fundamental','Trigonometria',$M,'Criar uma base confiável para Física e provas avançadas.',['razões no triângulo','círculo trigonométrico','ângulos notáveis','identidades básicas','lei dos senos/cossenos'],['desenhe o círculo','memorize valores só depois de entender','50 questões graduais'],'80 questões','≥ 80%','7–10 dias'),
          lc_module('Combinatória, probabilidade e estatística','Consolidação',$M,'Aprender contagem organizada e leitura de dados.',['princípio multiplicativo','permutação/arranjo/combinação','probabilidade','média/mediana','gráficos'],['enumere casos pequenos','40 questões de contagem/probabilidade','20 de estatística'],'75 questões','≥ 80%','6–8 dias'),
          lc_module('Consolidação da base','Consolidação',$M,'Transformar conteúdos isolados em repertório utilizável.',['mistura de assuntos','gestão de tempo','leitura de comando','checagem de solução'],['3 baterias de 30 questões','classifique cada erro: conceito/conta/leitura/tempo','refaça todos os erros após 7 dias'],'90 questões mistas','≥ 80% em 2 baterias consecutivas','7 dias'),
        ]
      ],
      'efomm'=>['nome'=>'EFOMM','icone'=>'EF','cor'=>'#1E5AA8','descricao'=>'Trilha de preparação para EFOMM. Estruture Matemática e Física com profundidade e mantenha Português/Inglês em treino contínuo; confirme sempre o edital do ano.','modulos'=>[
        lc_module('Nivelamento matemático','Fase 1',$M,'Fechar lacunas antes do nível EFOMM.',['álgebra','funções','trigonometria','geometria'],['use a trilha Base Forte nos pontos abaixo de 75%','40 questões de diagnóstico EFOMM'],'40–80 questões','≥ 80% nos pré-requisitos','1–2 semanas'),
        lc_module('Funções e equações','Fase 2',$M,'Resolver famílias de problemas algébricos.',['polinomiais','exponenciais','logarítmicas','modulares','inequações'],['resumo de transformações','60 questões EFOMM/semelhantes'],'60 questões','≥ 75%','1 semana'),
        lc_module('Trigonometria e geometria','Fase 2',$M,'Combinar identidades com visão geométrica.',['ciclo','equações trigonométricas','triângulos','circunferência','áreas/volumes'],['desenho obrigatório','2 baterias cronometradas'],'70 questões','≥ 75%','1–2 semanas'),
        lc_module('Combinatória, probabilidade e complexos','Fase 3',$M,'Cobrir tópicos de discriminação alta.',['contagem','binômio','probabilidade','complexos'],['30 questões por bloco','refazer erros sem consulta'],'80 questões','≥ 75%','1–2 semanas'),
        lc_module('Mecânica completa','Fase 2',$F,'Modelar movimentos e forças.',['cinemática','dinâmica','trabalho/energia','quantidade de movimento','gravitação'],['diagrama antes da fórmula','70 questões'],'70 questões','≥ 75%','2 semanas'),
        lc_module('Fluidos, termologia e ondas','Fase 3',$F,'Conectar fenômenos e equações.',['hidrostática','calorimetria','termodinâmica','MHS','ondas','óptica'],['mapa de grandezas','60 questões'],'60 questões','≥ 75%','1–2 semanas'),
        lc_module('Eletricidade e magnetismo','Fase 3',$F,'Dominar circuitos e campos.',['eletrostática','circuitos','potência','magnetismo','indução'],['desenhe circuitos','60 questões'],'60 questões','≥ 75%','1–2 semanas'),
        lc_module('Português de prova','Fase contínua',$P,'Ganhar pontos em interpretação e gramática contextual.',['interpretação','sintaxe','concordância','regência','pontuação','semântica'],['20 questões por semana','caderno de regras que você realmente erra'],'80 questões','≥ 80%','contínuo'),
        lc_module('Inglês instrumental','Fase contínua',$I,'Aumentar velocidade de leitura e vocabulário.',['reading','connectors','reference','verb tenses','vocabulary'],['leitura diária curta','20 questões por semana'],'80 questões','≥ 80%','contínuo'),
        lc_module('Provas completas EFOMM','Fase final','Geral','Treinar decisão sob tempo real.',['estratégia de ordem','tempo','chute consciente','revisão pós-prova'],['1 prova antiga por semana','corrigir no mesmo dia','refazer erros em 7 dias'],'6 provas completas','meta definida pela faixa de aprovação','6+ semanas'),
      ]],
      'epcar'=>['nome'=>'EPCAR','icone'=>'EP','cor'=>'#0E7C5B','descricao'=>'Matemática, Português, Inglês e redação com base sólida e muita prova antiga. Ajuste pesos e regras ao edital vigente.','modulos'=>[
        lc_module('Base matemática EPCAR','Fase 1',$M,'Eliminar lacunas que derrubam questões médias.',['aritmética','frações','razões','potências','equações'],['diagnóstico','50 questões de base'],'50 questões','≥ 85%','1 semana'),
        lc_module('Álgebra e funções','Fase 2',$M,'Ganhar fluidez algébrica.',['sistemas','2º grau','funções','inequações','polinômios'],['baterias de 20','refazer erros'],'80 questões','≥ 80%','1–2 semanas'),
        lc_module('Geometria e trigonometria','Fase 2',$M,'Resolver figuras sem depender de memorização cega.',['plana','espacial','semelhança','trigonometria'],['desenhos','60 questões'],'60 questões','≥ 80%','1–2 semanas'),
        lc_module('Português: interpretação','Fase 1',$P,'Ler comando e inferências com precisão.',['tipologia','coesão','semântica','figuras'],['1 texto por dia','40 questões'],'40 questões','≥ 85%','1 semana'),
        lc_module('Português: gramática aplicada','Fase 2',$P,'Consolidar regras recorrentes.',['morfologia','sintaxe','concordância','regência','crase','pontuação'],['caderno de erros','70 questões'],'70 questões','≥ 80%','2 semanas'),
        lc_module('Inglês EPCAR','Fase contínua',$I,'Ler com velocidade e reconhecer estruturas.',['reading','vocabulary','pronouns','verbs','prepositions','connectors'],['15 min leitura/dia','60 questões'],'60 questões','≥ 80%','contínuo'),
        lc_module('Redação e repertório','Fase contínua','Redação','Produzir texto controlado sob tempo.',['estrutura','tese','argumentação','coesão','revisão'],['1 redação semanal','reescrever introdução/conclusão após correção'],'6 redações','nota estável em 3 textos','6 semanas'),
        lc_module('Simulados EPCAR','Fase final','Geral','Integrar conteúdo e tempo.',['estratégia','gestão de prova','revisão'],['1 prova antiga semanal'],'6 provas','estabilidade de desempenho','6 semanas'),
      ]],
      'eear'=>['nome'=>'EEAR','icone'=>'EA','cor'=>'#7B3FA4','descricao'=>'Trilha equilibrada de Português, Inglês, Matemática e Física, com foco em velocidade e precisão.','modulos'=>[
        lc_module('Matemática essencial','Fase 1',$M,'Firmar cálculo e álgebra.',['aritmética','equações','funções','PA/PG'],['60 questões por blocos'],'60 questões','≥ 85%','1–2 semanas'),
        lc_module('Geometria e trigonometria EEAR','Fase 2',$M,'Ganhar pontos em visualização e relações métricas.',['plana','espacial','trigonometria','analítica'],['50 questões'],'50 questões','≥ 80%','1 semana'),
        lc_module('Física: mecânica','Fase 2',$F,'Resolver problemas com desenho e unidade.',['cinemática','dinâmica','energia','impulso'],['60 questões'],'60 questões','≥ 80%','1–2 semanas'),
        lc_module('Física: termologia, óptica e eletricidade','Fase 3',$F,'Cobrir os blocos restantes.',['termologia','ondas','óptica','eletrostática','circuitos'],['70 questões'],'70 questões','≥ 80%','2 semanas'),
        lc_module('Português EEAR','Fase contínua',$P,'Dominar gramática e interpretação.',['fonologia','morfologia','sintaxe','pontuação','semântica'],['80 questões'],'80 questões','≥ 85%','contínuo'),
        lc_module('Inglês EEAR','Fase contínua',$I,'Reconhecer estruturas e compreender textos.',['reading','grammar','vocabulary'],['leitura + 60 questões'],'60 questões','≥ 80%','contínuo'),
        lc_module('Baterias cronometradas','Fase final','Geral','Treinar ritmo de prova.',['mistura de disciplinas','tempo','revisão'],['3 baterias de 40','4 provas antigas'],'160+ questões','desempenho estável','4–6 semanas'),
      ]],
      'cn'=>['nome'=>'Colégio Naval','icone'=>'CN','cor'=>'#C25700','descricao'=>'Trilha ampla para Colégio Naval, com Matemática, Português, Inglês, Ciências e Estudos Sociais.','modulos'=>[
        lc_module('Matemática fundamental CN','Fase 1',$M,'Construir domínio escolar forte.',['aritmética','proporções','álgebra','equações'],['80 questões'],'80 questões','≥ 85%','2 semanas'),
        lc_module('Geometria e trigonometria CN','Fase 2',$M,'Consolidar figuras e relações.',['triângulos','polígonos','círculo','áreas','trigonometria'],['70 questões'],'70 questões','≥ 80%','2 semanas'),
        lc_module('Português: leitura e gramática','Fase 2',$P,'Ler com precisão e dominar norma-padrão.',['interpretação','morfologia','sintaxe','semântica'],['80 questões'],'80 questões','≥ 85%','2 semanas'),
        lc_module('Inglês CN','Fase contínua',$I,'Compreender textos e estruturas.',['reading','vocabulary','grammar'],['60 questões'],'60 questões','≥ 80%','contínuo'),
        lc_module('Ciências: Física','Fase 2','Ciências','Entender fenômenos básicos cobrados no nível da prova.',['movimento','forças','energia','eletricidade','óptica'],['50 questões'],'50 questões','≥ 80%','1–2 semanas'),
        lc_module('Ciências: Química e Biologia','Fase 2','Ciências','Cobrir matéria, transformações e vida.',['matéria','misturas','reações','ecologia','corpo humano'],['60 questões'],'60 questões','≥ 80%','1–2 semanas'),
        lc_module('Estudos Sociais','Fase 2','Estudos Sociais','Organizar História e Geografia por eixos.',['Brasil','cartografia','população','economia','território'],['linha do tempo + mapas','60 questões'],'60 questões','≥ 80%','2 semanas'),
        lc_module('Redação CN','Fase contínua','Redação','Escrever com estrutura e correção.',['tema','projeto de texto','coesão','revisão'],['1 texto semanal'],'6 redações','regularidade de nota','6 semanas'),
        lc_module('Provas completas CN','Fase final','Geral','Treinar dois dias/formatos conforme edital.',['tempo','ordem','resistência'],['6 provas antigas'],'6 provas','resultado estável','6 semanas'),
      ]],
      'ita'=>['nome'=>'ITA','icone'=>'IT','cor'=>'#B00020','descricao'=>'Trilha de alto nível. Base forte primeiro; depois aprofundamento matemático, físico e químico, com provas antigas como eixo central.','modulos'=>[
        lc_module('Pré-requisitos ITA','Fase 0',$M,'Garantir que a base não limite o avançado.',['álgebra','trigonometria','geometria','funções'],['diagnóstico de 100 questões','volte à Base Forte abaixo de 80%'],'100 questões','≥ 80%','2 semanas'),
        lc_module('Álgebra avançada','Fase 1',$M,'Dominar manipulação e demonstração.',['polinômios','complexos','sequências','inequações','equações funcionais introdutórias'],['listas longas','escrever soluções completas'],'100 questões','≥ 70% em nível ITA','3–4 semanas'),
        lc_module('Geometria e trigonometria avançadas','Fase 1',$M,'Resolver problemas não rotineiros.',['geometria euclidiana','analítica','trigonometria','vetores'],['tente 30–45 min antes da solução','refaça por método alternativo'],'100 questões','≥ 65–70%','4 semanas'),
        lc_module('Combinatória e probabilidade ITA','Fase 2',$M,'Construir argumentos de contagem.',['bijeções','inclusão-exclusão','recorrências simples','probabilidade'],['soluções discursivas'],'80 questões','≥ 70%','3 semanas'),
        lc_module('Mecânica ITA','Fase 1',$F,'Modelar sistemas com várias leis.',['cinemática vetorial','Newton','energia','momento','rotação','gravitação'],['deduza fórmulas','100 questões'],'100 questões','≥ 70%','4 semanas'),
        lc_module('Termodinâmica, ondas e óptica ITA','Fase 2',$F,'Aprofundar modelagem física.',['gases','termodinâmica','MHS','ondas','óptica geométrica/física'],['80 questões'],'80 questões','≥ 70%','3–4 semanas'),
        lc_module('Eletromagnetismo ITA','Fase 2',$F,'Dominar campo, potencial, circuitos e indução.',['eletrostática','circuitos','magnetismo','indução'],['100 questões'],'100 questões','≥ 70%','4 semanas'),
        lc_module('Química geral e físico-química','Fase 1',$Q,'Consolidar fundamentos quantitativos.',['estequiometria','gases','soluções','termoquímica','cinética','equilíbrio','eletroquímica'],['100 questões'],'100 questões','≥ 70%','4 semanas'),
        lc_module('Orgânica e inorgânica','Fase 2',$Q,'Reconhecer estruturas, reatividade e propriedades.',['ligações','funções','isomeria','reações','inorgânica descritiva'],['80 questões'],'80 questões','≥ 70%','3 semanas'),
        lc_module('Português, Inglês e redação','Fase contínua','Linguagens','Não entregar pontos fora das exatas.',['interpretação','gramática','reading','redação'],['leitura semanal','1 redação/semana','questões de provas antigas'],'contínuo','regularidade','contínuo'),
        lc_module('1ª fase: velocidade e corte','Fase final','Geral','Maximizar acertos com controle de risco.',['seleção de questões','tempo','chute','checagem'],['provas antigas integrais'],'8 provas','faixa de corte com margem','8 semanas'),
        lc_module('2ª fase: solução completa','Fase final','Geral','Treinar desenvolvimento legível e rigoroso.',['organização de solução','justificativa','álgebra limpa'],['provas discursivas','reescrever soluções ruins'],'6 provas','soluções completas no tempo','6 semanas'),
      ]],
      'esa'=>['nome'=>'ESA','icone'=>'ES','cor'=>'#4b6b35','descricao'=>'Trilha orientada a Matemática, Português, História/Geografia do Brasil, Inglês e redação conforme especialidade/edital.','modulos'=>[
        lc_module('Matemática ESA','Fase 1',$M,'Ganhar precisão nos tópicos recorrentes.',['aritmética','álgebra','funções','geometria','probabilidade'],['100 questões'],'100 questões','≥ 85%','2–3 semanas'),
        lc_module('Português ESA','Fase 1',$P,'Dominar gramática e interpretação.',['classes','sintaxe','concordância','regência','pontuação','semântica'],['100 questões'],'100 questões','≥ 85%','2–3 semanas'),
        lc_module('História do Brasil','Fase 2','História/Geografia','Organizar fatos em causalidade e cronologia.',['Colônia','Império','República','Brasil contemporâneo'],['linha do tempo','80 questões'],'80 questões','≥ 80%','2 semanas'),
        lc_module('Geografia do Brasil','Fase 2','História/Geografia','Relacionar espaço, economia e população.',['relevo/clima','população','urbanização','economia','regiões'],['mapas mudos','80 questões'],'80 questões','≥ 80%','2 semanas'),
        lc_module('Inglês ESA','Fase contínua',$I,'Ler textos e estruturas cobradas.',['reading','grammar','vocabulary'],['50 questões'],'50 questões','≥ 80%','contínuo'),
        lc_module('Redação','Fase contínua','Redação','Treinar texto dentro do padrão do edital.',['estrutura','argumentação','coesão','norma'],['1 texto/semana'],'6 textos','nota estável','6 semanas'),
        lc_module('Simulados ESA','Fase final','Geral','Ajustar velocidade e estratégia.',['ordem','tempo','revisão'],['6 provas'],'6 provas','resultado estável','6 semanas'),
      ]],
      'espcex'=>['nome'=>'EsPCEx','icone'=>'EX','cor'=>'#556b2f','descricao'=>'Preparação ampla para a prova da EsPCEx: Exatas, Linguagens e Humanas integradas.','modulos'=>[
        lc_module('Matemática EsPCEx','Fase 1',$M,'Construir repertório completo.',['funções','trigonometria','matrizes','geometrias','combinatória','probabilidade'],['120 questões'],'120 questões','≥ 80%','3 semanas'),
        lc_module('Física EsPCEx','Fase 1',$F,'Cobrir mecânica a eletricidade.',['mecânica','termologia','ondas','óptica','eletricidade'],['100 questões'],'100 questões','≥ 80%','3 semanas'),
        lc_module('Química EsPCEx','Fase 1',$Q,'Dominar cálculos e conceitos.',['geral','físico-química','inorgânica','orgânica'],['100 questões'],'100 questões','≥ 80%','3 semanas'),
        lc_module('Português e literatura','Fase 2',$P,'Interpretar e aplicar língua/literatura.',['interpretação','gramática','literatura'],['100 questões'],'100 questões','≥ 85%','2–3 semanas'),
        lc_module('História','Fase 2','História/Geografia','Organizar Brasil e geral.',['Brasil','geral','séculos XIX–XXI'],['80 questões'],'80 questões','≥ 80%','2 semanas'),
        lc_module('Geografia','Fase 2','História/Geografia','Ler fenômenos e mapas.',['Brasil','geopolítica','população','economia','ambiente'],['80 questões'],'80 questões','≥ 80%','2 semanas'),
        lc_module('Inglês','Fase contínua',$I,'Manter alta precisão.',['reading','grammar','vocabulary'],['60 questões'],'60 questões','≥ 80%','contínuo'),
        lc_module('Redação','Fase contínua','Redação','Escrever sob critérios objetivos.',['tese','argumentação','coesão','revisão'],['1 texto/semana'],'8 textos','nota estável','8 semanas'),
        lc_module('Provas completas EsPCEx','Fase final','Geral','Integrar carga extensa.',['estratégia','tempo','resistência'],['6 provas antigas'],'6 provas','resultado estável','6 semanas'),
      ]],
      'afa'=>['nome'=>'AFA','icone'=>'AF','cor'=>'#305f8d','descricao'=>'Matemática, Física, Português, Inglês e redação com domínio técnico e velocidade.','modulos'=>[
        lc_module('Matemática AFA','Fase 1',$M,'Aprofundar álgebra, funções e geometria.',['funções','trigonometria','geometrias','complexos','combinatória'],['120 questões'],'120 questões','≥ 80%','3 semanas'),
        lc_module('Física AFA: mecânica','Fase 1',$F,'Resolver problemas de movimento e conservação.',['cinemática','dinâmica','energia','momento','gravitação'],['80 questões'],'80 questões','≥ 80%','2 semanas'),
        lc_module('Física AFA: demais áreas','Fase 2',$F,'Completar termologia a eletromagnetismo.',['fluidos','termo','ondas','óptica','eletricidade'],['100 questões'],'100 questões','≥ 80%','3 semanas'),
        lc_module('Português AFA','Fase contínua',$P,'Manter precisão em língua.',['interpretação','gramática','semântica'],['80 questões'],'80 questões','≥ 85%','contínuo'),
        lc_module('Inglês AFA','Fase contínua',$I,'Ler com velocidade.',['reading','vocabulary','grammar'],['70 questões'],'70 questões','≥ 85%','contínuo'),
        lc_module('Redação AFA','Fase contínua','Redação','Escrever com clareza e consistência.',['projeto','argumentação','coesão','revisão'],['1 redação/semana'],'8 textos','nota estável','8 semanas'),
        lc_module('Simulados AFA','Fase final','Geral','Treinar a prova inteira.',['tempo','ordem','checagem'],['6 provas antigas'],'6 provas','resultado estável','6 semanas'),
      ]],
      'ime'=>['nome'=>'IME','icone'=>'IM','cor'=>'#6b3f2d','descricao'=>'Trilha avançada para Matemática, Física e Química, com Linguagens e treino discursivo.','modulos'=>[
        lc_module('Base avançada IME','Fase 0',$M,'Chegar ao nível em que listas avançadas são produtivas.',['álgebra','trigonometria','geometria','funções'],['diagnóstico','100 questões'],'100 questões','≥ 80%','2 semanas'),
        lc_module('Matemática IME I','Fase 1',$M,'Aprofundar álgebra e funções.',['polinômios','complexos','sequências','funções','inequações'],['100 questões com soluções completas'],'100 questões','≥ 70%','4 semanas'),
        lc_module('Matemática IME II','Fase 2',$M,'Dominar geometria e combinatória.',['plana','espacial','analítica','trigonometria','combinatória'],['100 questões'],'100 questões','≥ 70%','4 semanas'),
        lc_module('Física IME I','Fase 1',$F,'Aprofundar mecânica.',['cinemática','dinâmica','energia','momento','rotação','gravitação'],['100 questões'],'100 questões','≥ 70%','4 semanas'),
        lc_module('Física IME II','Fase 2',$F,'Aprofundar termo, ondas e eletromagnetismo.',['termo','fluidos','ondas','óptica','eletricidade','magnetismo'],['100 questões'],'100 questões','≥ 70%','4 semanas'),
        lc_module('Química IME I','Fase 1',$Q,'Dominar físico-química quantitativa.',['estequiometria','soluções','termo','cinética','equilíbrio','eletroquímica'],['100 questões'],'100 questões','≥ 70%','4 semanas'),
        lc_module('Química IME II','Fase 2',$Q,'Consolidar orgânica e inorgânica.',['estrutura','reações','isomeria','inorgânica'],['80 questões'],'80 questões','≥ 70%','3 semanas'),
        lc_module('Linguagens e redação IME','Fase contínua','Linguagens','Evitar perdas fora das exatas.',['português','inglês','redação'],['questões semanais','1 redação/semana'],'contínuo','regularidade','contínuo'),
        lc_module('Provas objetiva e discursiva','Fase final','Geral','Treinar seleção e desenvolvimento.',['tempo','solução formal','checagem'],['8 provas antigas'],'8 provas','faixa competitiva','8 semanas'),
      ]],
      'eeam'=>['nome'=>'EEAM','icone'=>'AM','cor'=>'#126782','descricao'=>'Trilha para Escolas de Aprendizes-Marinheiros com base forte e prática intensiva. Confira a composição exata no edital vigente.','modulos'=>[
        lc_module('Matemática básica EEAM','Fase 1',$M,'Eliminar erros simples e ganhar velocidade.',['aritmética','frações','porcentagem','proporção','equações'],['100 questões'],'100 questões','≥ 90%','2 semanas'),
        lc_module('Álgebra e geometria EEAM','Fase 2',$M,'Consolidar tópicos médios.',['funções básicas','sistemas','plana','espacial'],['80 questões'],'80 questões','≥ 85%','2 semanas'),
        lc_module('Português EEAM','Fase 1',$P,'Dominar interpretação e gramática.',['interpretação','ortografia','morfologia','sintaxe','pontuação'],['120 questões'],'120 questões','≥ 85%','2–3 semanas'),
        lc_module('Ciências/Física','Fase 2','Ciências','Revisar fenômenos e cálculos conforme edital.',['mecânica básica','energia','calor','eletricidade'],['60 questões'],'60 questões','≥ 80%','1–2 semanas'),
        lc_module('Ciências/Química','Fase 2','Ciências','Revisar matéria e transformações conforme edital.',['estrutura da matéria','misturas','reações','química cotidiana'],['50 questões'],'50 questões','≥ 80%','1–2 semanas'),
        lc_module('Provas antigas EEAM','Fase final','Geral','Treinar formato real.',['tempo','leitura de comando','revisão'],['6 provas'],'6 provas','resultado estável','6 semanas'),
      ]],
      'cfn'=>['nome'=>'CFN','icone'=>'CF','cor'=>'#5a6e3d','descricao'=>'Trilha de preparação para o Corpo de Fuzileiros Navais, com ênfase em base escolar, velocidade e provas oficiais; valide matérias no edital do seu processo.','modulos'=>[
        lc_module('Matemática operacional CFN','Fase 1',$M,'Ganhar segurança e velocidade.',['operações','frações','razão','porcentagem','equações'],['100 questões'],'100 questões','≥ 90%','2 semanas'),
        lc_module('Matemática aplicada CFN','Fase 2',$M,'Resolver problemas e geometria.',['problemas','sistemas','geometria','medidas'],['80 questões'],'80 questões','≥ 85%','2 semanas'),
        lc_module('Português CFN','Fase 1',$P,'Maximizar pontuação em língua.',['interpretação','ortografia','classes','sintaxe','pontuação'],['120 questões'],'120 questões','≥ 90%','2–3 semanas'),
        lc_module('Leitura e interpretação sob tempo','Fase 2',$P,'Evitar perda por leitura apressada.',['comando','inferência','vocabulário','coesão'],['40 textos/questões'],'40 questões','≥ 85%','1 semana'),
        lc_module('Revisão orientada pelo edital','Fase 2','Geral','Cobrir eventuais blocos adicionais do edital vigente.',['conteúdo específico do processo','atualização anual'],['transforme cada item do edital em checklist de domínio'],'100% do edital','nenhum tópico sem diagnóstico','1 semana'),
        lc_module('Provas oficiais CFN','Fase final','Geral','Treinar o formato real e reduzir erros bobos.',['tempo','ordem','checagem'],['6 provas antigas'],'6 provas','resultado estável','6 semanas'),
      ]],
    ];
}

/** @return array<int,array<string,string>> */
function papiro_guide_articles(): array {
    return [
      ['slug'=>'comecar','titulo'=>'Nunca estudei sozinho: por onde começo?','icone'=>'01','tempo'=>'10 min','texto'=>'<h2>Seu primeiro objetivo é criar um sistema, não estudar 8 horas.</h2><p>Na primeira semana, descubra seu nível, escolha um concurso principal e monte uma rotina que caiba na vida real. Use blocos de 50–90 minutos, com começo e fim definidos.</p><h3>Primeiros 7 dias</h3><ol><li>Dia 1: faça um diagnóstico curto de Matemática e Português sem consultar.</li><li>Dia 2: classifique erros em <b>conceito, conta, interpretação ou falta de tempo</b>.</li><li>Dias 3–5: estude apenas dois assuntos-base e resolva questões logo depois.</li><li>Dia 6: bateria mista de 30 questões.</li><li>Dia 7: revisão do caderno de erros e planejamento da semana seguinte.</li></ol><h3>Regra de ouro</h3><p>Uma sessão só termina quando você consegue responder: “o que aprendi, onde errei e o que vou revisar?”.</p>'],
      ['slug'=>'rotina','titulo'=>'Como montar uma rotina que você consegue cumprir','icone'=>'02','tempo'=>'9 min','texto'=>'<p>Comece pelo tempo disponível, não pelo cronograma ideal. Separe a semana em <b>blocos fixos</b> e deixe 10–20% do tempo livre para atrasos.</p><h3>Modelo de sessão de 2 horas</h3><ul><li>10 min — revisão ativa do último estudo;</li><li>45 min — teoria com anotações curtas;</li><li>50 min — questões do mesmo assunto;</li><li>15 min — correção e caderno de erros.</li></ul><p>Se tiver 4 horas, faça dois ciclos com matérias diferentes. Não aumente horas enquanto ainda estiver desperdiçando o bloco atual.</p>'],
      ['slug'=>'teoria','titulo'=>'Como estudar teoria sem ficar só assistindo aula','icone'=>'03','tempo'=>'8 min','texto'=>'<p>Aula é entrada de informação; aprendizagem exige saída. A cada 20–30 minutos, feche o material e escreva de memória os conceitos, fórmulas e condições de uso.</p><h3>Fluxo</h3><ol><li>Defina uma pergunta: “o que preciso conseguir fazer ao final?”</li><li>Estude um bloco curto.</li><li>Faça 3–5 exemplos sem olhar.</li><li>Resolva 10–20 questões.</li><li>Volte à teoria apenas nas lacunas reveladas pelas questões.</li></ol><p>Evite copiar páginas inteiras. Seu resumo deve ser pequeno o bastante para revisão rápida.</p>'],
      ['slug'=>'questoes','titulo'=>'Como resolver questões para realmente evoluir','icone'=>'04','tempo'=>'10 min','texto'=>'<p>Não use questão apenas para medir nota. Use para descobrir padrões de erro.</p><h3>Método em quatro passos</h3><ol><li><b>Tente de verdade:</b> sem abrir resolução cedo demais.</li><li><b>Marque confiança:</b> certeza, dúvida ou chute.</li><li><b>Corrija a causa:</b> conceito, cálculo, interpretação ou estratégia.</li><li><b>Refaça:</b> 24 horas e 7 dias depois se errou.</li></ol><p>Uma questão acertada no chute também merece revisão. Uma questão errada por conta básica pede treino diferente de uma errada por conteúdo novo.</p>'],
      ['slug'=>'mat','titulo'=>'Matemática do zero até uma base forte','icone'=>'05','tempo'=>'12 min','texto'=>'<p>Se você tem muitas lacunas, não comece por assuntos “bonitos” de prova avançada. Construa uma escada.</p><h3>Ordem sugerida</h3><p>Operações e sinais → frações → razão/proporção → porcentagem → potências/raízes → linguagem algébrica → equações → fatoração → 2º grau → geometria → funções → trigonometria.</p><h3>Critério para avançar</h3><p>Faça pelo menos duas baterias com 80–85% de acerto no nível atual. Se você erra por conta, fique mais um pouco. Se erra apenas problemas novos e consegue explicar a solução depois, avance e mantenha revisão.</p><p>A trilha <b>Base Forte em Matemática</b> já está organizada nessa sequência e contém metas de questões e domínio.</p>'],
      ['slug'=>'fis','titulo'=>'Física: sair da fórmula decorada para o raciocínio','icone'=>'06','tempo'=>'10 min','texto'=>'<p>Antes de escolher fórmula, faça quatro coisas: desenhe, liste dados com unidades, diga qual sistema está analisando e escreva a lei física em palavras.</p><h3>Ordem recomendada</h3><p>Vetores/cinemática → Newton → trabalho e energia → momento → gravitação → fluidos/termologia → ondas/óptica → eletricidade → magnetismo.</p><p>Quando errar, identifique se faltou matemática ou física. Se o problema foi álgebra, volte ao pré-requisito em vez de assistir a mesma aula de Física novamente.</p>'],
      ['slug'=>'quim','titulo'=>'Química: como organizar teoria e cálculo','icone'=>'07','tempo'=>'9 min','texto'=>'<p>Química alterna linguagem, modelos e cálculo. Estude os três juntos.</p><ul><li>Faça uma folha de grandezas e unidades.</li><li>Em estequiometria, escreva a equação balanceada antes da proporção.</li><li>Em equilíbrio/eletroquímica, identifique o fenômeno antes da fórmula.</li><li>Em orgânica, desenhe estruturas e classifique funções.</li></ul><p>Use questões para transformar nomes em padrões reconhecíveis.</p>'],
      ['slug'=>'erros','titulo'=>'Caderno de erros que não vira um cemitério','icone'=>'08','tempo'=>'8 min','texto'=>'<p>Registre apenas o necessário para impedir a repetição do erro:</p><ol><li>assunto e número da questão;</li><li>por que errou em uma frase;</li><li>regra/ideia correta em até três linhas;</li><li>uma ação: revisar teoria ou refazer questões.</li></ol><p>Revise o caderno antes do simulado semanal. Apague mentalmente a resposta e refaça a questão, em vez de apenas reler.</p>'],
      ['slug'=>'revisao','titulo'=>'Revisão sem perder metade da semana','icone'=>'09','tempo'=>'8 min','texto'=>'<p>Use revisões curtas e ativas. Um modelo simples é <b>24h → 7 dias → 30 dias</b>, mas adapte ao erro.</p><p>Conteúdo dominado: poucas questões de manutenção. Conteúdo fraco: revisão mais próxima + bateria. Conteúdo esquecido: volte à teoria mínima necessária.</p><p>Priorize o que você erra e o que tem peso no concurso; não revise tudo com a mesma intensidade.</p>'],
      ['slug'=>'sim','titulo'=>'Como usar simulados sem desperdiçar prova antiga','icone'=>'10','tempo'=>'10 min','texto'=>'<p>Prova antiga é recurso valioso. Antes de gastar uma, já tenha estudado boa parte do conteúdo.</p><h3>Durante</h3><p>Respeite tempo, pausas e materiais permitidos. Marque questões em três grupos: resolvo agora, volto depois, chute controlado.</p><h3>Depois</h3><p>Gaste quase o mesmo tempo corrigindo. Calcule precisão por matéria, identifique questões perdidas por tempo e selecione 3 prioridades para a semana seguinte.</p>'],
      ['slug'=>'edital','titulo'=>'Como transformar o edital em plano de estudo','icone'=>'11','tempo'=>'9 min','texto'=>'<p>O edital é a fonte de verdade. Copie os tópicos para uma planilha/checklist e marque cada um como: não visto, estudando, praticando, dominado.</p><p>As trilhas do Papiro organizam um caminho pedagógico, mas <b>não substituem a conferência do edital vigente</b>. Sempre ajuste matérias, pesos, critérios e datas do seu ano.</p>'],
      ['slug'=>'semana','titulo'=>'Como planejar e fechar uma semana','icone'=>'12','tempo'=>'8 min','texto'=>'<h3>No domingo ou início da semana</h3><p>Defina 3 entregas mensuráveis, como “80 questões de função”, “2 listas de mecânica” e “1 redação”. Evite metas vagas como “estudar bastante”.</p><h3>No fim da semana</h3><p>Compare planejado x feito, taxa de acerto, horas reais e principais erros. Leve apenas 1–2 pendências para a próxima semana; excesso de atraso torna o plano inútil.</p>'],
      ['slug'=>'dominio','titulo'=>'Como usar o mapa de domínio sem se enganar','icone'=>'13','tempo'=>'7 min','texto'=>'<p>O índice de domínio do Papiro combina <b>precisão</b> com <b>volume de prática</b>. Acertar 3 de 3 questões não significa dominar uma matéria; é preciso sustentar o desempenho em um conjunto maior.</p><h3>Como agir</h3><ul><li><b>Reforçar base:</b> volte ao pré-requisito e faça exemplos simples.</li><li><b>Aprendendo:</b> teoria curta + bateria direcionada.</li><li><b>Praticando:</b> misture assuntos e reduza o tempo por questão.</li><li><b>Dominado:</b> manutenção semanal e simulados.</li></ul><p>Use o mapa para escolher prioridades, não para competir com um número.</p>'],
      ['slug'=>'espacada','titulo'=>'Revisão espaçada no caderno de erros','icone'=>'14','tempo'=>'7 min','texto'=>'<p>Depois de errar, o Papiro agenda a primeira revisão. Ao marcar uma revisão como feita, o intervalo aumenta. A lógica é simples: revisar perto do esquecimento e espaçar quando a lembrança fica estável.</p><h3>Durante a revisão</h3><ol><li>Tente resolver sem olhar sua anotação.</li><li>Explique o princípio usado.</li><li>Se acertar com segurança, marque a revisão.</li><li>Se ainda depender da resposta antiga, volte à teoria mínima.</li></ol><p>Não transforme revisão em releitura passiva. O objetivo é recuperar a solução da memória.</p>'],
    ];
}

/** Atualiza trilhas/guia preservando progresso (módulos mantêm o mesmo id quando ordem já existe). */
function ensure_learning_content(PDO $pdo): array {
    $trails = papiro_learning_trails();
    $findTrail = $pdo->prepare('SELECT id FROM trilhas WHERE slug=? LIMIT 1');
    $insTrail = $pdo->prepare('INSERT INTO trilhas (slug,nome,icone,cor,descricao,ativo) VALUES (?,?,?,?,?,1)');
    $updTrail = $pdo->prepare('UPDATE trilhas SET nome=?,icone=?,cor=?,descricao=?,ativo=1 WHERE id=?');
    $findModule = $pdo->prepare('SELECT id FROM trilha_modulos WHERE trilha_id=? AND ordem=? LIMIT 1');
    $insModule = $pdo->prepare('INSERT INTO trilha_modulos (trilha_id,titulo,ordem,descricao) VALUES (?,?,?,?)');
    $updModule = $pdo->prepare('UPDATE trilha_modulos SET titulo=?,descricao=? WHERE id=?');
    $trailCount=$moduleCount=0;
    foreach($trails as $slug=>$t){
        $findTrail->execute([$slug]); $tid=$findTrail->fetchColumn();
        if($tid){$updTrail->execute([$t['nome'],$t['icone'],$t['cor'],$t['descricao'],(int)$tid]);}
        else{$insTrail->execute([$slug,$t['nome'],$t['icone'],$t['cor'],$t['descricao']]);$tid=(int)$pdo->lastInsertId();}
        $trailCount++;
        foreach($t['modulos'] as $i=>$m){
            $ord=$i+1; $desc=json_encode($m,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $findModule->execute([(int)$tid,$ord]); $mid=$findModule->fetchColumn();
            if($mid)$updModule->execute([$m['titulo'],$desc,(int)$mid]); else $insModule->execute([(int)$tid,$m['titulo'],$ord,$desc]);
            $moduleCount++;
        }
    }
    $findGuide=$pdo->prepare('SELECT id FROM guia_artigos WHERE slug=? LIMIT 1');
    $insGuide=$pdo->prepare('INSERT INTO guia_artigos (slug,titulo,icone,tempo,texto) VALUES (?,?,?,?,?)');
    $updGuide=$pdo->prepare('UPDATE guia_artigos SET titulo=?,icone=?,tempo=?,texto=? WHERE id=?');
    $guideCount=0;
    foreach(papiro_guide_articles() as $g){
        $findGuide->execute([$g['slug']]);$gid=$findGuide->fetchColumn();
        if($gid)$updGuide->execute([$g['titulo'],$g['icone'],$g['tempo'],$g['texto'],(int)$gid]); else $insGuide->execute([$g['slug'],$g['titulo'],$g['icone'],$g['tempo'],$g['texto']]);
        $guideCount++;
    }
    return ['trilhas'=>$trailCount,'modulos'=>$moduleCount,'guia'=>$guideCount];
}

/** Atualiza trilhas/guia somente quando esta versão pedagógica mudar. */
function ensure_learning_content_current(PDO $pdo, string $version='2026.09-v25'): array {
    if (function_exists('papiro_ensure_runtime_schema')) papiro_ensure_runtime_schema($pdo);
    try {
        $st=$pdo->prepare("SELECT meta_value FROM app_meta WHERE meta_key='learning_content_version' LIMIT 1");
        $st->execute();
        if ((string)($st->fetchColumn() ?: '') === $version) return ['cached'=>1];
    } catch (Throwable $e) {}
    $r=ensure_learning_content($pdo);
    try {
        $pdo->prepare("DELETE FROM app_meta WHERE meta_key='learning_content_version'")->execute();
        $pdo->prepare('INSERT INTO app_meta (meta_key,meta_value) VALUES (?,?)')->execute(['learning_content_version',$version]);
    } catch (Throwable $e) {}
    $r['cached']=0;
    return $r;
}
