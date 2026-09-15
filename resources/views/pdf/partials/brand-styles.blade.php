{{--
    Shared page format for every generated PDF: the "Connected for Change"
    header band on the first page and the green/teal footer band on every page.

    The page keeps real top and bottom margins so a document that runs past one
    page still has breathing room at the top of page two and never prints under
    the footer. The header is pulled up into the top margin, and the footer is
    fixed into the bottom margin — the standard DomPDF pattern for both.
--}}
@page { margin: 36px 0 56px; }
body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; line-height: 1.42; color: #221f1f; margin: 0; }
.content { padding: 22px 48px 0; }
a { color: #255325; }

/* The 16:9 banner clipped to a band, cropped so "for Change" clears the green strip. */
.hero { position: relative; height: 226px; margin-top: -36px; overflow: hidden; background: #efe7e0; }
.hero-banner { position: absolute; left: 0; top: -125px; width: 100%; }
.hero-green-band { position: absolute; left: 0; right: 0; bottom: 0; height: 40px; background: #255325; }
.hero-web { position: absolute; right: 48px; bottom: 12px; color: #fff; font-weight: bold; font-size: 11px; }

/* Solid blocks rather than a gradient: DomPDF drops a gradient on a fixed element. */
.footer-band { position: fixed; left: 0; right: 0; bottom: -56px; height: 30px; color: #fff; font-size: 9pt; font-weight: 700; line-height: 30px; }
.footer-band .web { position: absolute; left: 0; top: 0; width: 75%; height: 30px; background: #255325; text-align: right; }
.footer-band .web span { padding-right: 32px; }
.footer-band .note { position: absolute; left: 48px; top: 0; font-weight: 400; font-size: 8pt; }
.footer-band .tag { position: absolute; right: 0; top: 0; width: 25%; height: 30px; background: #10a7ae; text-align: center; }
