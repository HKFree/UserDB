## Opravy

- #294 - Formulář AP odmítne subnet, jehož maska není celé číslo 0 až 32 zapsané číslicemi
  Zápis jako /024, /+24 nebo /3e1 dřív prošel a různé části aplikace pak rozsah subnetu četly různě. Chybová hláška nově říká, v jakém tvaru subnet zadat. Mezery na okrajích subnetu a gateway, třeba po vložení ze schránky, už nevadí.
