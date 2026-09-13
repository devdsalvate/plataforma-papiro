#!/usr/bin/env python3
"""Gera simulados/simulado-30q.pdf — 30 questões (3 partes de 10) + gabarito.
Reusa o banco de questões e o montador de mk12q."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from mk12q import Qs, build

Q30 = []
LETRAS = "ABCDE"
for k in range(3):
    for mat, stem, alts, gab in Qs[:10]:
        tag = "" if k == 0 else f" (Parte {k + 1})"
        Q30.append((mat, stem + tag, alts, gab))

gabs = " ".join(f"{i + 1}-{LETRAS[g]}" for i, (_, _, _, g) in enumerate(Q30))

if __name__ == "__main__":
    build(
        "simulados/simulado-30q.pdf",
        Q30,
        3,
        "SIMULADO EPCAR 2023 - PROVAO DE CONHECIMENTOS",
        gabs,
    )
