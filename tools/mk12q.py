#!/usr/bin/env python3
"""Gera simulados/simulado-12q.pdf — 12 questões em 3 páginas + gabarito.
PDF puro com stdlib (zlib). Texto em WinAnsi (latin-1)."""
import zlib, os

Qs = [
    ("Matemática", "Seja x um número real tal que 3x + 7 = 22. Além disso, considere que o dobro de x somado com 5 resulta em um valor que dividido por 3 é igual a 7. Essas condições confirmam a solução da equação original proposta no comando desta questão objetiva de álgebra elementar.",
     ["x = 3", "x = 5", "x = 7", "x = 4", "x = 6"], 1),
    ("Matemática", "Um retângulo tem perímetro igual a 34 cm e um dos lados mede 7 cm. Sabendo que a área de um retângulo é o produto de seus lados adjacentes, e que o perímetro é a soma de todos os lados, determine a área desse retângulo em centímetros quadrados.",
     ["70 cm²", "60 cm²", "56 cm²", "48 cm²", "42 cm²"], 0),
    ("Física", "Um carro parte do repouso e atinge 72 km/h em 4 segundos, com aceleração constante. Convertendo a velocidade para m/s e aplicando a definição de aceleração média como a variação da velocidade dividida pelo intervalo de tempo, qual é a aceleração do carro?",
     ["2 m/s²", "4 m/s²", "5 m/s²", "8 m/s²", "10 m/s²"], 2),
    ("Física", "Um bloco de 2 kg está em repouso sobre uma superfície horizontal sem atrito. Aplica-se sobre ele uma força horizontal constante de 10 N durante 3 segundos. Usando a segunda lei de Newton e a equação da velocidade no movimento uniformemente variado, a velocidade final é:",
     ["5 m/s", "10 m/s", "12 m/s", "15 m/s", "30 m/s"], 3),
    ("Matemática", "A soma dos ângulos internos de um polígono regular é 1260°. Lembrando que a soma dos ângulos internos de um polígono de n lados é dada por (n - 2) · 180°, e que em um polígono regular todos os ângulos internos são iguais, quantos lados tem esse polígono?",
     ["7 lados", "8 lados", "9 lados", "10 lados", "12 lados"], 2),
    ("Química", "Uma solução foi preparada dissolvendo-se 20 g de NaCl em água suficiente para completar 500 mL de solução. Sabendo que a concentração comum é a razão entre a massa do soluto e o volume da solução, a concentração dessa solução em g/L é igual a:",
     ["10 g/L", "20 g/L", "25 g/L", "40 g/L", "100 g/L"], 3),
    ("Matemática", "Em uma progressão aritmética, o primeiro termo é 5 e a razão é 4. Recordando que o termo geral é an = a1 + (n - 1) · r e que a soma dos n primeiros termos é Sn = n · (a1 + an) / 2, a soma dos 10 primeiros termos dessa progressão vale:",
     ["210", "230", "250", "270", "290"], 1),
    ("Português", "Assinale a alternativa em que a concordância verbal está correta, conforme a norma culta da língua portuguesa, observando especialmente os casos de sujeito composto, expressões partitivas e verbos impessoiros que costumam gerar dúvidas em provas de concurso:",
     ["Houveram muitos problemas na festa.", "Fazem dois anos que não o vejo.", "Existe sérias dúvidas sobre o caso.", "Choveu canivetes naquele dia.", "Devem haver soluções melhores."], 3),
    ("Matemática", "Uma loja oferece 20% de desconto à vista, e sobre o valor já com desconto o cliente ainda ganha mais 10% por ser cadastrado no programa de fidelidade. Se o preço original de um produto é R$ 500,00, o valor final pago pelo cliente cadastrado que compra à vista é:",
     ["R$ 350,00", "R$ 360,00", "R$ 370,00", "R$ 380,00", "R$ 400,00"], 1),
    ("Física", "Um resistor de 10 ohm é submetido a uma tensão de 20 V. Pela primeira lei de Ohm, a corrente que o atravessa pode ser calculada, e pela definição de potência elétrica dissipada em um resistor, a potência transformada em calor por efeito Joule vale:",
     ["20 W", "30 W", "40 W", "50 W", "200 W"], 2),
    ("Matemática", "Dois dados honestos de seis faces são lançados simultaneamente. A probabilidade é definida como a razão entre o número de casos favoráveis e o número de casos possíveis. A probabilidade de a soma dos pontos ser igual a 7 é de:",
     ["1/12", "1/9", "1/6", "5/36", "7/36"], 2),
    ("Inglês", "Choose the alternative that correctly completes the sentence, observing the proper use of verb tenses, prepositions and conjunctions according to standard English grammar as typically required in Brazilian military entrance examinations:",
     ["She has went to school yesterday.", "They didn't knew the answer.", "He speaks English very well.", "We was happy with the result.", "I have saw that movie twice."], 2),
]
GAB = "1-B 2-A 3-C 4-D 5-C 6-D 7-B 8-D 9-B 10-C 11-C 12-C"

def esc(s):
    return s.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")

def page_stream(lines, title=None):
    ops = ["BT /F1 11 Tf 50 800 Td 15 TL"]
    if title:
        ops.append(f"({esc(title)}) Tj T* T*")
    for ln in lines:
        # quebra linhas longas em ~95 chars (sem cortar palavras)
        words, cur = ln.split(" "), ""
        for w in words:
            if len(cur) + 1 + len(w) > 95:
                ops.append(f"({esc(cur)}) Tj T*")
                cur = w
            else:
                cur = (cur + " " + w).strip()
        if cur:
            ops.append(f"({esc(cur)}) Tj T*")
        ops.append("T*")
    ops.append("ET")
    return zlib.compress("\n".join(ops).encode("latin-1"))

def build(path, questions=None, per_page=4, title="SIMULADO EPCAR 2023 - PROVA DE CONHECIMENTOS", gab=None):
    questions = questions if questions is not None else Qs
    gab = gab if gab is not None else GAB
    pages_content = []
    npages = (len(questions) + per_page - 1) // per_page
    for pg in range(npages):
        lines = []
        for i in range(per_page):
            n = pg * per_page + i
            if n >= len(questions):
                break
            mat, stem, alts, g = questions[n]
            lines.append(f"QUESTÃO {n+1} [{mat}]")
            lines.append(stem)
            for j, a in enumerate(alts):
                lines.append(f"{'ABCDE'[j]}) {a}")
        pages_content.append(page_stream(lines, title if pg == 0 else None))
    # última página: gabarito
    pages_content.append(page_stream([f"GABARITO OFICIAL: {gab}"], "GABARITO"))
    # monta PDF
    objs = []
    n_pages = len(pages_content)
    # 1: catalog, 2: pages, 3: font, depois pares (page, contents)
    objs.append("<< /Type /Catalog /Pages 2 0 R >>")
    kids = " ".join(f"{4 + i*2} 0 R" for i in range(n_pages))
    objs.append(f"<< /Type /Pages /Kids [{kids}] /Count {n_pages} >>")
    objs.append("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>")
    for i, data in enumerate(pages_content):
        objs.append(f"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {5 + i*2} 0 R >>")
        objs.append(("stream", data))
    out = bytearray(b"%PDF-1.4\n")
    offs = []
    for idx, o in enumerate(objs, start=1):
        offs.append(len(out))
        if isinstance(o, tuple):
            out += f"{idx} 0 obj\n<< /Length {len(o[1])} /Filter /FlateDecode >>\nstream\n".encode("latin-1")
            out += o[1] + b"\nendstream\nendobj\n"
        else:
            out += f"{idx} 0 obj\n{o}\nendobj\n".encode("latin-1")
    xref = len(out)
    out += f"xref\n0 {len(objs)+1}\n0000000000 65535 f \n".encode("latin-1")
    for of in offs:
        out += f"{of:010d} 00000 n \n".encode("latin-1")
    out += f"trailer\n<< /Size {len(objs)+1} /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n".encode("latin-1")
    os.makedirs(os.path.dirname(path) or ".", exist_ok=True)
    open(path, "wb").write(bytes(out))
    print(f"OK: {path} ({len(out)} bytes, {n_pages} páginas)")

if __name__ == "__main__":
    build("simulados/simulado-12q.pdf")
