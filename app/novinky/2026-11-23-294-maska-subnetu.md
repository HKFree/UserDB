## Opravy

- #294 - Formulář AP odmítne subnet, jehož maska není celé číslo 0 až 32 zapsané číslicemi
  Zápis jako /024, /+24 nebo /3e1 dřív prošel a různé části aplikace pak rozsah subnetu četly různě. Chybová hláška nově říká, v jakém tvaru subnet zadat.
- Formuláře AP a člena už neodmítnou IP adresu, subnet ani gateway kvůli mezeře na okraji
  Mezery, tabulátory a konce řádků kolem hodnoty, třeba po vložení ze schránky, formulář sám ořízne. Platí i pro pole Přidat všechny ip ze subnetu a Přidat rozsah ip u člena.
