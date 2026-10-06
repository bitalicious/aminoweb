import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useRef, useState } from "react";
import { ChevronRight, FileText, FlaskConical, Menu, Minus, Plus, ShieldCheck, ShoppingBag, Truck, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import logoAsset from "@/assets/amino-heaven-logo.jpeg.asset.json";
import heroVialImage from "@/assets/BLANKLOGO.png.asset.json";
import bpcTbImage from "@/assets/products/BPC157_TB500_5mg_5mg.webp.asset.json";
import bpcImage from "@/assets/products/BPC-157_10mg.webp.asset.json";
import cjcIpaImage from "@/assets/products/CJC1295NoDACIPAMORELIN5mg_5mg.webp.asset.json";
import cjcImage from "@/assets/products/CJC1295NoDAC.webp.asset.json";
import glp1Image from "@/assets/products/GLP1-SM10mg.webp.asset.json";
import glp2TenImage from "@/assets/products/GLP2-TR10mg.webp.asset.json";
import glp2SixtyImage from "@/assets/products/GLP2-TR60mg.webp.asset.json";
import glp3Image from "@/assets/products/GLP3-RT10mg.webp.asset.json";
import glp3SixtyImage from "@/assets/products/GLP3-RT60mg.png";
import ghkImage from "@/assets/products/GHK-CU100mg.webp.asset.json";
import glowImage from "@/assets/products/Glow70mg.webp.asset.json";
import ipamorelinImage from "@/assets/products/Ipamorelin10mg.webp.asset.json";
import melanotanImage from "@/assets/products/Melanotanll10mg.webp.asset.json";
import motsCImage from "@/assets/products/motsc40mg.webp.asset.json";
import nadImage from "@/assets/products/Nad_1000mg.webp.asset.json";
import pt141Image from "@/assets/products/pt14110mg.webp.asset.json";
import sermorelinImage from "@/assets/products/SERMORELIN5mg.webp.asset.json";
import tb500Image from "@/assets/products/TB-50010mg.webp.asset.json";
import klowImage from "@/assets/products/KLOW80mg.png.asset.json";
import kpvImage from "@/assets/products/KPV10mg.webp.asset.json";
import tesaImage from "@/assets/products/Tesa10mg.webp.asset.json";

export const Route = createFileRoute("/")({
  component: Index,
  head: () => ({
    meta: [
      { title: "Amino Heaven — Analytical-Grade Research Peptides" },
      { name: "description", content: "Shop American-synthesized, analytical-grade research peptides with independent Certificates of Analysis from accredited U.S. laboratories." },
      { property: "og:title", content: "Amino Heaven — Research Compounds" },
      { property: "og:description", content: "American-synthesized research peptides with independent Certificates of Analysis from accredited U.S. laboratories." },
      { property: "og:type", content: "website" },
      { property: "og:url", content: "/" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
    links: [{ rel: "canonical", href: "/" }],
  }),
});

type ProductVariant = { dose: string; price: number; image: string };
type Product = { name: string; variants: ProductVariant[] };

const products: Product[] = [
  { name: "BPC-157/TB-500", variants: [{ dose: "5mg/5mg", price: 49.95, image: bpcTbImage.url }] },
  { name: "BPC-157", variants: [{ dose: "10mg", price: 49.95, image: bpcImage.url }] },
  { name: "CJC-1295 (No DAC) IPAMORELIN", variants: [{ dose: "5mg/5mg", price: 49.95, image: cjcIpaImage.url }] },
  { name: "CJC-1295 (No DAC)", variants: [{ dose: "10mg", price: 49.95, image: cjcImage.url }] },
  { name: "GLP1-SM", variants: [{ dose: "10mg", price: 49.95, image: glp1Image.url }] },
  { name: "GLP2-TR", variants: [{ dose: "10mg", price: 49.95, image: glp2TenImage.url }, { dose: "60mg", price: 199.95, image: glp2SixtyImage.url }] },
  { name: "GLP3-RT", variants: [{ dose: "10mg", price: 69.95, image: glp3Image.url }, { dose: "60mg", price: 249.95, image: glp3SixtyImage }] },
  { name: "GHK-CU", variants: [{ dose: "100mg", price: 44.95, image: ghkImage.url }] },
  { name: "GLOW", variants: [{ dose: "70mg", price: 99.95, image: glowImage.url }] },
  { name: "IPAMORELIN", variants: [{ dose: "10mg", price: 49.95, image: ipamorelinImage.url }] },
  { name: "KLOW", variants: [{ dose: "80mg", price: 109.95, image: klowImage.url }] },
  { name: "KPV", variants: [{ dose: "10mg", price: 45.95, image: kpvImage.url }] },
  { name: "MELANOTAN II", variants: [{ dose: "10mg", price: 39.95, image: melanotanImage.url }] },
  { name: "MOTS-c", variants: [{ dose: "40mg", price: 134.95, image: motsCImage.url }] },
  { name: "NAD+", variants: [{ dose: "1000mg", price: 64.95, image: nadImage.url }] },
  { name: "PT-141", variants: [{ dose: "10mg", price: 39.95, image: pt141Image.url }] },
  { name: "SERMORELIN", variants: [{ dose: "5mg", price: 39.95, image: sermorelinImage.url }] },
  { name: "TB-500", variants: [{ dose: "10mg", price: 39.95, image: tb500Image.url }] },
  { name: "Tesamorelin", variants: [{ dose: "10mg", price: 59.95, image: tesaImage.url }] },
];

const trustItems = [
  { icon: FlaskConical, title: "≥99% Purity", text: "HPLC & MS verified per batch" },
  { icon: ShieldCheck, title: "Third-Party COAs", text: "Independent lab reports published" },
  { icon: Truck, title: "Ships same day if ordered before 1 PM PST", text: "Prompt order processing" },
];

const listPrice = (price: number) => Math.round(price * 1.3 * 100) / 100;
const tierPrice = (unitPrice: number, count: number) => Math.round(unitPrice * count * (count === 2 ? 0.95 : count === 5 ? 0.85 : 1) * 100) / 100;
const formatPrice = (price: number) => price.toFixed(2);

const AGE_GATE_KEY = "amino-heaven-age-verified";

function AgeVerificationModal({ onEnter }: { onEnter: () => void }) {
  const [isOfAge, setIsOfAge] = useState(false);
  const [researchOnly, setResearchOnly] = useState(false);
  const dialogRef = useRef<HTMLDivElement>(null);
  const canEnter = isOfAge && researchOnly;

  useEffect(() => {
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    dialogRef.current?.focus();
    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, []);

  const keepFocusInside = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (event.key !== "Tab") return;
    const controls = dialogRef.current?.querySelectorAll<HTMLElement>(
      'input:not([disabled]), button:not([disabled])',
    );
    if (!controls?.length) return;
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (!first || !last) return;
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };

  return (
    <div className="age-gate" role="presentation">
      <div
        ref={dialogRef}
        className="age-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="age-dialog-title"
        aria-describedby="age-dialog-description"
        tabIndex={-1}
        onKeyDown={keepFocusInside}
      >
        <div className="age-dialog-mark" aria-hidden="true"><FlaskConical /></div>
        <p className="eyebrow">Restricted access</p>
        <h2 id="age-dialog-title">Age Verification</h2>
        <p id="age-dialog-description">
          Amino Heaven sells research peptides exclusively for laboratory and analytical research. You must be 21 or older to enter.
        </p>
        <div className="age-confirmations">
          <label>
            <input type="checkbox" checked={isOfAge} onChange={(event) => setIsOfAge(event.target.checked)} />
            <span>I confirm that I am 21 years of age or older.</span>
          </label>
          <label>
            <input type="checkbox" checked={researchOnly} onChange={(event) => setResearchOnly(event.target.checked)} />
            <span>I acknowledge that all products are for laboratory research use only and are not for human consumption.</span>
          </label>
        </div>
        <div className="age-actions">
          <Button disabled={!canEnter} onClick={onEnter}>I Agree and Enter</Button>
          <Button variant="outline" onClick={() => window.location.assign("about:blank")}>Exit</Button>
        </div>
      </div>
    </div>
  );
}

function HelixBackdrop() {
  return (
    <div className="helix-field" aria-hidden="true">
      <svg viewBox="0 0 1200 620" preserveAspectRatio="xMidYMid slice">
        <g className="helix-track">
          <path d="M-60 520C160 520 160 80 380 80S600 520 820 520s220-440 440-440" />
          <path d="M-60 80c220 0 220 440 440 440S600 80 820 80s220 440 440 440" />
          {Array.from({ length: 15 }, (_, i) => (
            <line key={i} x1={i * 92 - 60} y1={90 + ((i % 4) * 95)} x2={i * 92 + 60} y2={530 - ((i % 4) * 95)} />
          ))}
        </g>
        <g className="molecule-nodes">
          {[[105,145],[235,470],[390,120],[535,480],[690,155],[845,450],[1005,120],[1120,405]].map(([x,y]) => <circle key={`${x}-${y}`} cx={x} cy={y} r="8" />)}
        </g>
      </svg>
    </div>
  );
}

function ProductCard({ product }: { product: Product }) {
  const [variantIndex, setVariantIndex] = useState(0);
  const [pack, setPack] = useState(1);
  const [quantity, setQuantity] = useState(1);
  const variant = product.variants[variantIndex] ?? product.variants[0];
  if (!variant) return null;
  const displayName = `${product.name} ${variant.dose}`;
  const packPrice = tierPrice(variant.price, pack);
  const fiveVialPrice = tierPrice(variant.price, 5);
  return (
    <article className="product-card">
      <a href="#catalog" className="product-image"><img src={variant.image} alt={`${displayName} research vial`} /></a>
      <div className="product-copy">
        <div className="flex items-start justify-between gap-3">
          <h3>{displayName}</h3><span className="stock"><span />In Stock</span>
        </div>
        <p className="eyebrow mt-4">Research compound</p>
        {product.variants.length > 1 && <div className="variant-selector mt-3" aria-label={`${product.name} strength`}>
          {product.variants.map((option, index) => <Button type="button" variant="outline" key={option.dose} aria-pressed={variantIndex === index} onClick={() => setVariantIndex(index)} className={variantIndex === index ? "active" : ""}>{option.dose}</Button>)}
        </div>}
        <div className="price-line mt-2"><del>${formatPrice(listPrice(variant.price))}</del><strong>${formatPrice(variant.price)}</strong><span>/ vial</span></div>
        <p className="bulk-price mt-1"><span>5 vials = </span><del>${formatPrice(listPrice(fiveVialPrice))}</del> <strong>${formatPrice(fiveVialPrice)}</strong></p>
        <Button className="mt-3 w-full" onClick={() => undefined}><FileText size={15}/>View COA</Button>
        <div className="pack-grid mt-4">
          {[1,2,5].map((count) => {
            const sellingPrice = tierPrice(variant.price, count);
            return <button type="button" key={count} onClick={() => setPack(count)} className={pack === count ? "active" : ""}><b>{count} VIAL{count > 1 ? "S" : ""}</b><span><del>${formatPrice(listPrice(sellingPrice))}</del> ${formatPrice(sellingPrice)}</span>{count > 1 && <em>Save {count === 2 ? 5 : 15}%</em>}</button>;
          })}
        </div>
        <div className="mt-4 grid grid-cols-[104px_minmax(0,1fr)] gap-2">
          <div className="qty"><button aria-label="Decrease quantity" onClick={() => setQuantity(Math.max(1, quantity - 1))}><Minus size={14}/></button><span>{quantity}</span><button aria-label="Increase quantity" onClick={() => setQuantity(quantity + 1)}><Plus size={14}/></button></div>
          <Button onClick={() => undefined}><ShoppingBag size={15}/>Add {variant.dose}</Button>
        </div>
        <p className="mt-3 text-right text-xs font-semibold">Total: ${formatPrice(packPrice * quantity)}</p>
      </div>
    </article>
  );
}

function Index() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [ageVerified, setAgeVerified] = useState(false);

  useEffect(() => {
    setAgeVerified(window.sessionStorage.getItem(AGE_GATE_KEY) === "true");
  }, []);

  const enterStore = () => {
    window.sessionStorage.setItem(AGE_GATE_KEY, "true");
    setAgeVerified(true);
  };

  return (
    <div className="min-h-screen overflow-hidden bg-background text-foreground">
      {!ageVerified && <AgeVerificationModal onEnter={enterStore} />}
      <div className="sale-ticker"><div>Fall Sale 30% Off Sitewide - No Code Needed</div></div>
      <header className="site-header">
        <a href="#top" className="brand"><img src={logoAsset.url} alt="Amino Heaven" /><span><b>AMINO HEAVEN</b><small>RESEARCH USE ONLY</small></span></a>
        <nav className="desktop-nav"><a href="#catalog">Shop</a><a href="#quality">Quality / COAs</a><a href="#about">About</a><a href="#contact">Contact</a></nav>
        <div className="flex items-center gap-1"><Button variant="ghost" size="icon" aria-label="Shopping bag"><ShoppingBag size={19}/></Button><Button variant="ghost" size="icon" className="md:hidden" aria-label="Toggle menu" onClick={() => setMenuOpen(!menuOpen)}>{menuOpen ? <X/> : <Menu/>}</Button></div>
        {menuOpen && <nav className="mobile-nav"><a href="#catalog" onClick={() => setMenuOpen(false)}>Shop</a><a href="#quality" onClick={() => setMenuOpen(false)}>Quality / COAs</a><a href="#about" onClick={() => setMenuOpen(false)}>About</a><a href="#contact" onClick={() => setMenuOpen(false)}>Contact</a></nav>}
      </header>

      <main id="top">
        <section className="hero-band">
          <HelixBackdrop />
          <div className="hero-inner">
            <div className="hero-copy">
              <p className="eyebrow">Independent U.S. Lab Verification</p>
              <h1>Research compounds you can actually verify</h1>
              <p>Amino Heaven delivers American-synthesized, analytical-grade research peptides—each batch accompanied by an independent Certificate of Analysis from accredited U.S. laboratories. Explore. Discover. Pioneer.</p>
              <div className="mt-7 flex flex-wrap gap-3"><Button asChild><a href="#catalog">Browse the catalog <ChevronRight size={16}/></a></Button><Button asChild variant="outline"><a href="#quality">View COAs</a></Button></div>
              <p className="mt-5 text-xs font-medium text-muted-foreground">For laboratory research use only—not for human consumption.</p>
            </div>
            <div className="hero-product"><div className="halo-ring"/><img src={heroVialImage.url} alt="Amino Heaven laboratory research vial"/><span>VERIFIED<br/>RESEARCH<br/>MATERIAL</span></div>
          </div>
        </section>

        <section className="trust-strip">
          {trustItems.map(({ icon: Icon, title, text }) => <div key={title}><Icon size={23}/><span><b>{title}</b><small>{text}</small></span></div>)}
        </section>

        <section id="catalog" className="content-section">
          <div className="section-heading"><div><p className="eyebrow">Curated compounds</p><h2>Featured compounds</h2><p>Best-moving vials available this week.</p></div><a href="#catalog">View all <ChevronRight size={16}/></a></div>
          <div className="product-grid">{products.map((product) => <ProductCard key={product.name} product={product}/>)}</div>
        </section>

        <section id="quality" className="quality-band">
          <HelixBackdrop />
          <div className="quality-inner"><div><p className="eyebrow">Documented at every step</p><h2>Quality you can verify</h2></div><div className="quality-points"><article><span>01</span><h3>Batch-level testing</h3><p>Every lot is analysed by an independent laboratory for identity, purity and endotoxin levels before release.</p></article></div></div>
        </section>
      </main>

      <footer id="contact">
        <div className="footer-grid"><div className="footer-brand"><b>AMINO HEAVEN</b><p>Discover. Innovate. Elevate.</p><p>Research support available Monday–Friday.</p><a href="mailto:hello@aminoheaven.com">hello@aminoheaven.com</a></div><div><h3>Quick links</h3><a href="#catalog">Shop Catalog</a><a href="#quality">Quality Control / COAs</a><a href="#top">Track Order</a><a href="#about">About Us</a></div><div><h3>Newsletter</h3><p>Restock alerts, new COAs and promo vials.</p><form onSubmit={(event) => event.preventDefault()}><input type="email" aria-label="Email address" placeholder="Email address"/><Button type="submit">Subscribe</Button></form></div><div><h3>Compliance</h3><p>For laboratory research use only—not for human consumption.</p><p>Scam warning: anyone who contacts you first to solicit an order is a scammer.</p></div></div>
        <div className="compliance-statement">
          <p>All products distributed through this platform are designated strictly for in vitro research, laboratory analysis, and development purposes. Under no circumstances are these compounds intended, formulated, or approved for human consumption, veterinary administration, or therapeutic application of any kind.</p>
          <p>Statements on this website have not been evaluated by the U.S. Food and Drug Administration. Neither the information presented nor the materials supplied are intended to diagnose, treat, cure, mitigate, or prevent any medical condition or disease.</p>
          <p>Amino Heaven operates solely as an independent biochemical reagent supplier. Amino Heaven is not a compounding pharmacy or chemical compounding facility as defined under Section 503A of the Federal Food, Drug, and Cosmetic Act (FD&amp;C Act), nor an outsourcing facility as defined under Section 503B of the FD&amp;C Act. All inventory is cataloged and sold exclusively for controlled laboratory, analytical, and academic research use by qualified personnel.</p>
        </div>
        <div className="legal"><span>© 2026 Amino Heaven. All rights reserved.</span><span>RESEARCH USE ONLY · NOT FOR HUMAN OR ANIMAL CONSUMPTION</span></div>
      </footer>
    </div>
  );
}
