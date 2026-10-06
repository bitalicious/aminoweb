#!/usr/bin/env python3
"""
Build the product seed file used by `wp amino-heaven import-products`.

The catalog is transcribed from the source Lovable storefront's single route
(`amino-heaven-theme-main/src/routes/index.tsx`), which is the authoritative
list of compounds, strengths and prices. Image asset ids are resolved from
`assets-source/manifest.tsv`, which is produced by scripts/fetch-source-assets.sh.

Run from the repository root:

    python3 scripts/build-catalog-json.py

The output is committed. It is migration *input* only — WooCommerce becomes the
source of truth for the catalog as soon as the import runs.
"""

from __future__ import annotations

import json
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
MANIFEST = ROOT / "assets-source" / "manifest.tsv"
OUTPUT = ROOT / "wordpress/wp-content/themes/amino-heaven/inc/data/catalog.json"

SOURCE_ORIGIN = "https://amino-heaven-theme.lovable.app"

# name, slug, category, sku suffix, [(dose, unit price, image filename)]
#
# Prices, strengths and imagery are exactly those in src/routes/index.tsx.
CATALOG = [
    ("BPC-157/TB-500", "bpc-157-tb-500", "peptide-blends", [
        ("5mg/5mg", 49.95, "BPC157_TB500_5mg_5mg.webp"),
    ]),
    ("BPC-157", "bpc-157", "research-peptides", [
        ("10mg", 49.95, "BPC-157_10mg.webp"),
    ]),
    ("CJC-1295 (No DAC) IPAMORELIN", "cjc-1295-no-dac-ipamorelin", "peptide-blends", [
        ("5mg/5mg", 49.95, "CJC1295NoDACIPAMORELIN5mg_5mg.webp"),
    ]),
    ("CJC-1295 (No DAC)", "cjc-1295-no-dac", "research-peptides", [
        ("10mg", 49.95, "CJC1295NoDAC.webp"),
    ]),
    ("GLP1-SM", "glp1-sm", "glp-research", [
        ("10mg", 49.95, "GLP1-SM10mg.webp"),
    ]),
    ("GLP2-TR", "glp2-tr", "glp-research", [
        ("10mg", 49.95, "GLP2-TR10mg.webp"),
        ("60mg", 199.95, "GLP2-TR60mg.webp"),
    ]),
    ("GLP3-RT", "glp3-rt", "glp-research", [
        ("10mg", 69.95, "GLP3-RT10mg.webp"),
        ("60mg", 249.95, "GLP3-RT60mg.png"),
    ]),
    ("GHK-CU", "ghk-cu", "research-peptides", [
        ("100mg", 44.95, "GHK-CU100mg.webp"),
    ]),
    ("GLOW", "glow", "research-peptides", [
        ("70mg", 99.95, "Glow70mg.webp"),
    ]),
    ("IPAMORELIN", "ipamorelin", "research-peptides", [
        ("10mg", 49.95, "Ipamorelin10mg.webp"),
    ]),
    ("KLOW", "klow", "research-peptides", [
        ("80mg", 109.95, "KLOW80mg.png"),
    ]),
    ("KPV", "kpv", "research-peptides", [
        ("10mg", 45.95, "KPV10mg.webp"),
    ]),
    ("MELANOTAN II", "melanotan-ii", "research-peptides", [
        ("10mg", 39.95, "Melanotanll10mg.webp"),
    ]),
    ("MOTS-c", "mots-c", "research-peptides", [
        ("40mg", 134.95, "motsc40mg.webp"),
    ]),
    ("NAD+", "nad", "research-peptides", [
        ("1000mg", 64.95, "Nad_1000mg.webp"),
    ]),
    ("PT-141", "pt-141", "research-peptides", [
        ("10mg", 39.95, "pt14110mg.webp"),
    ]),
    ("SERMORELIN", "sermorelin", "research-peptides", [
        ("5mg", 39.95, "SERMORELIN5mg.webp"),
    ]),
    ("TB-500", "tb-500", "research-peptides", [
        ("10mg", 39.95, "TB-50010mg.webp"),
    ]),
    ("Tesamorelin", "tesamorelin", "research-peptides", [
        ("10mg", 59.95, "Tesa10mg.webp"),
    ]),
]

CATEGORIES = [
    {
        "slug": "research-peptides",
        "name": "Research Peptides",
        "parent": "",
        "description": "Analytical-grade research peptides supplied for in vitro laboratory analysis.",
    },
    {
        "slug": "peptide-blends",
        "name": "Peptide Blends",
        "parent": "research-peptides",
        "description": "Multi-compound research blends supplied as a single vial.",
    },
    {
        "slug": "glp-research",
        "name": "GLP Research",
        "parent": "research-peptides",
        "description": "GLP-class research compounds supplied for laboratory analysis.",
    },
]


def load_asset_ids() -> dict[str, str]:
    """Map image filename -> Lovable asset id, from the fetch manifest."""
    if not MANIFEST.exists():
        print(f"error: {MANIFEST} not found. Run scripts/fetch-source-assets.sh first.", file=sys.stderr)
        sys.exit(1)

    mapping: dict[str, str] = {}
    with MANIFEST.open(encoding="utf-8") as handle:
        next(handle, None)  # header
        for line in handle:
            parts = line.rstrip("\n").split("\t")
            if len(parts) < 3:
                continue
            asset_id, _pointer, downloaded = parts[0], parts[1], parts[2]
            mapping[pathlib.Path(downloaded).name] = asset_id
    return mapping


def main() -> None:
    asset_ids = load_asset_ids()

    products = []
    missing_images = []

    for name, slug, category, doses in CATALOG:
        dose_entries = []
        for dose, unit_price, image in doses:
            if image not in asset_ids:
                missing_images.append(image)
            dose_entries.append(
                {
                    "dose": dose,
                    "unitPrice": unit_price,
                    "image": image,
                    "imageUrl": (
                        f"{SOURCE_ORIGIN}/__l5e/assets-v1/{asset_ids[image]}/{image}"
                        if image in asset_ids
                        else ""
                    ),
                }
            )

        products.append(
            {
                "name": name,
                "slug": slug,
                "sku": "AH-" + slug.upper(),
                "category": category,
                "featured": True,
                "doses": dose_entries,
            }
        )

    if missing_images:
        print(f"warning: no asset id for: {', '.join(sorted(set(missing_images)))}", file=sys.stderr)

    catalog = {
        "schema": 1,
        "description": (
            "Amino Heaven catalog seed. Transcribed from the source Lovable storefront "
            "(amino-heaven-theme-main/src/routes/index.tsx). Used once by "
            "`wp amino-heaven import-products` to create native WooCommerce variable "
            "products; WooCommerce is the source of truth thereafter."
        ),
        "sourceOrigin": SOURCE_ORIGIN,
        "attributes": {
            "dose": {"slug": "dose", "label": "Strength"},
            "pack": {"slug": "pack", "label": "Pack Size"},
        },
        "pricePolicy": {
            "note": (
                "The source storefront derives every displayed price from the 1-vial unit "
                "price. listPriceFactor produces the struck-through 'sitewide sale' price "
                "(the source multiplies the selling price by 1.3); tiers apply the bulk "
                "discount for 2 and 5 vials. Prices are rounded to 2 decimals the same way "
                "the source's Math.round(x * 100) / 100 does. The resulting values are "
                "written as WooCommerce regular/sale variation prices, so the cart total is "
                "always recalculated by WooCommerce."
            ),
            "listPriceFactor": 1.3,
            "tiers": [
                {"pack": "1 Vial", "count": 1, "factor": 1.0, "label": "1 Vial"},
                {"pack": "2 Vials", "count": 2, "factor": 0.95, "label": "2 Vials"},
                {"pack": "5 Vials", "count": 5, "factor": 0.85, "label": "5 Vials"},
            ],
        },
        "categories": CATEGORIES,
        "products": products,
    }

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps(catalog, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    variations = sum(len(p["doses"]) * len(catalog["pricePolicy"]["tiers"]) for p in products)
    print(f"wrote {OUTPUT.relative_to(ROOT)}")
    print(f"  products   : {len(products)}")
    print(f"  categories : {len(CATEGORIES)}")
    print(f"  variations : {variations}")
    print(f"  images     : {sum(len(p['doses']) for p in products)}")


if __name__ == "__main__":
    main()
