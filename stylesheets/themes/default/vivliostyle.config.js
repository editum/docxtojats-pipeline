module.exports = {
  entry: "article.html",
  output: "article.pdf",
  theme: "@vivliostyle/theme-academic",
  pdf: {
    format: "A4",
    spread: "none",
    baseFontSize: "10pt",
    pdfSerifFamily: "Times New Roman",
    pdfSansFamily: "Arial",
    pdfMonoFamily: "Courier New",
    marginTop: "2cm",
    marginBottom: "2cm",
    marginLeft: "2cm",
    marginRight: "2cm"
  },
  lang: "es",
  direction: "ltr"
};
