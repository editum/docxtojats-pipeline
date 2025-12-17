# Estudio conversión XML ➔ HTML ➔ PDF

## 1. Pipelines de conversión

Un **pipeline** de conversión consta de una cadena de pasos definidos para aplicar diferentes transformaciones (XSLT u otras operaciones) a un XML y producir un documento nuevo.

### XSLT 2.0: XProc (XML Pipeline Language)

- Los pipelines se definen en ficheros **.xpl** que indican los pasos a seguir.
- Estos pueden incluir transformaciones **XSLT**, validaciones (ej. Schematron), o incluso ejecución de comandos externos.
- Se procesan con intérpretes como **Calabash**, que usa **Saxon** internamente para las transformaciones XSLT.

> 🔎 Nota: **Saxon** también permite definir pipelines, pero usando su propio lenguaje. Algunas funciones no están soportadas en la versión gratuita **Saxon-HE**.

### XSLT 3.0: SaxonPE / SaxonEE

- **Saxon-PE** y **Saxon-EE** tienen soporte completo de **XSLT 3.0**.
- Incluyen funciones avanzadas como `saxon:assign` y `saxon:evaluate`.
- Son versiones **de pago**, orientadas a proyectos profesionales o empresariales.

---

## 2. Conversión XSL → HTML+CSS → PDF

Este método consiste en transformar primero el XML en HTML (con XSLT) y después convertir ese HTML a PDF.

- Se pueden usar varias transformaciones intermedias antes de aplicar la hoja final de estilos.
- Es sencillo, pero el control sobre el PDF final depende de la herramienta usada para la conversión.

Ejemplo:  
- Con **wkhtmltopdf** se pueden añadir headers y footers en HTML, con imágenes y estilos CSS.

**Pasos típicos:**

1. **XML**
2. Plantillas **XSL** + hoja de estilos **CSS** → HTML
3. Procesar con **SaxonHE** o **Calabash** → HTML
4. Convertir el **HTML** a **PDF** con herramientas como: `ebook-converter` (Calibre), `wkhtmltopdf`, `PrinceXML`, etc.

A la hora de convertir a PDF desde HTML sería posible usar un CSS diferente para darle otro formato distinto, la única desventaja es que seguramente no se puedan controlar cosas como tablas que aparezcan cortadas entre páginas, saltos de página, etc. Todo esto ya dependerá más del conversor usado.

---

## 3. Conversión XSL → XSL-FO → PDF

Aquí se generan ficheros **XSL-FO (Formatting Objects)**, que describen con detalle el formato y estilo del documento.

- Es el método más potente porque ofrece control total sobre márgenes, tipografía, saltos de página, tablas, etc.
- Requiere un motor de FO para la conversión final.

**Pasos típicos:**

1. **XML**
2. Plantillas **XSL** → fichero **XSL-FO**
3. Procesar el FO → **PDF** con: `fop`, **Antenna House**, **RenderX**, etc.

> ⚠️ La herramienta gratuita **Apache FOP** es usable, pero no implementa todas las funcionalidades de XSL-FO y puede dar fallos en documentos complejos.

---

## 4. Conversión a otros formatos E-Book

Partiendo de un **PDF** o un **HTML**, se puede convertir a otros formatos de libro electrónico:

- Usando `ebook-converter` de **Calibre**
- O herramientas similares

Esto permite exportar a **EPUB**, **MOBI**, etc., adaptados a distintos dispositivos.

---

## 5. Herramientas

### Alternativas XSL
- **Calabash** (gratis, XSLT 2.0 vía XProc)
- **Saxon-HE** (gratis, XSLT 2.0/3.0 básico)
- **Saxon-PE / Saxon-EE** (pago, XSLT 3.0 completo, extensiones avanzadas)

### Alternativas XSL-FO a FOP
- **Apache FOP** (gratis, limitado)
- **RenderX XEP** (pago, muy sólido)
- **Antenna House Formatter** (pago, el más completo)
- **xmlroff** (gratis, obsoleto y limitado)

### Alternativas HTML+CSS
- **PrinceXML** (pago, profesional)
- **Vivliostyle** (gratis, open source, moderno)
- **wkhtmltopdf** (gratis, más limitado)

### 📊 Comparativa rápida de herramientas y usos

| Producto | Precio base aprox. | Tipo de pago | Uso principal |
| --- | --- | --- | --- |
| **Calabash (XProc 1.0)** | Gratis | Open Source (LGPL) | Motor de **XProc**: orquesta pipelines XML. Encadena validaciones (Schematron), XSLT (con Saxon), y generación de PDF (con FOP/Prince). No se mantiene activamente, pero aún se usa. |
| **Apache FOP** | Gratis | Open Source (Apache 2.0) | Implementación de referencia de XSL-FO. Limitado, pero usable en entornos académicos o prototipo. |
| **wkhtmltopdf** | Gratis | Open Source (LGPL) | Convierte HTML → PDF usando WebKit. Soporta CSS, pero no FO. Útil en layouts simples. |
| **Vivliostyle** | Gratis | Open Source (AGPL) | HTML+CSS → PDF (basado en navegador). Muy usado en libros académicos. Soporta paginación CSS (Paged Media). |
| **Antenna House (Standalone Std)** | ~$1,250 + $250/año | Pago único + soporte | XSL-FO y CSS. Completo y estable, usado en editoriales. |
| **Antenna House (Server Std)** | ~$5,000 + $1,000/año | Pago único | Producción de alto volumen. |
| **RenderX XEP Server** | ~$4,600 + 20% soporte | Pago único + anual | Motor FO profesional, sólido. |
| **RenderX XEP Dev (single-core)** | ~$2,524 | Pago único | Licencia para desarrollo y testing. |
| **PrinceXML Desktop** | ~$495 | Pago único | HTML+CSS → PDF, orientado a individuos. |
| **PrinceXML Academic Server** | ~$1,900 | Pago único | Licencia con descuento académico. |
| **PrinceXML Standard Server** | ~$3,800 | Pago único | Producción de servidor. |
| **Prince (Enterprise / Cloud)** | desde ~$2,000/año | Suscripción anual | Uso en infraestructuras cloud. |
| **Saxon-HE (Home Edition)** | Gratis | Open Source (MPL 2.0) | XSLT 2.0/3.0 básico, XPath, XQuery. |
| **Saxon-PE (Professional Edition)** | £280 (~$350) | Licencia perpetua (por CPU/dev) | XSLT 3.0 con extensiones (`saxon:assign`, `evaluate`), streaming parcial. |
| **Saxon-EE (Enterprise Edition)** | £600–£6,000 (~$700–$7,500) según uso | Licencia perpetua o suscripción | Procesamiento de alto rendimiento, streaming completo, optimización, schema-aware, XSLT 3.0 avanzado. |

---

## 6. Ejemplos de conversión

Estos ejemplos de conversión se han hecho usando plantillas XSLT de [ncbi/JATSPreviewStylesheets](https://github.com/ncbi/JATSPreviewStylesheets)

Lo ideal sería tener uno o varias plantillas propias según las necesidades.

Puesto que no tenemos ningún procesador de `xsl-fo` que funcione con las salidas generadas, las conversiones se harán a partir del html generado por XProc

---

### XML → HTML con XProc

```ssh
calabash -i source=apa-libre-mendeley.xml ../../resources/JATSPreviewStylesheets/shells/xproc/jats-APAcit-html.xpl  > xproc.html
```
- Salida: [xproc.html](xslt/xproc.html)
- Salida con CSS aplicado: [xproc-css.html](xslt/xproc-css.html)

---

### XML → HTML con SaxonHE

```ssh
saxon -s:xslt/apa-libre-mendeley.xml -xsl:../resources/JATSPreviewStylesheets/shells/saxon/jats-APAcit-html.xsl -o:xslt/saxon.html
```
> ⚠️ Con saxonHE no se puede aplicar este pipeline en concreto porque usa funciones como `saxon:assign`

Sin pipeline:

```ssh
saxon -s:xslt/apa-libre-mendeley.xml -xsl:../resources/JATSPreviewStylesheets/xslt/main/jats-html.xsl -o:xslt/saxon.html
```
- Salida: [saxon.html](xslt/saxon.html)
- Salida con CSS aplicado: [saxon.html](xslt/saxon.html)

---

### HTML+CSS → PDF con calibre

Conversión con calibre aplicando algunas de las opciones que permite para el header y el footer:

- No se lleva muy bien con los estilos en el header y footer, aunque si parece coger posiciones absolutas.
- Las imagenes que aparezcan en el header o footer deben tener rutas absolutas.
- Sustituye los siguientes keywords: _PAGENUM_, _TITLE_, _AUTHOR_ and _SECTION_

#### Ejemplo con xproc-css.html

Con CSS se podría estilizar bastante.

```ssh
/opt/calibre/ebook-convert xslt/xproc-css.html xslt/xproc-css-calibre.pdf \
    --pdf-footer-template "<div style='width:100%; text-align:center; font-size:9pt;'> \
        Página _PAGENUM_ \
    </div>"
```
- Salida: [xproc-css-calibre.pdf](xslt/xproc-css-calibre.pdf)

#### Ejemplo con xproc.html añadiendo header y footer con calibre

```ssh
/opt/calibre/ebook-convert xslt/xproc.html xslt/xproc-calibre.pdf \
    --base-font-size 10 \
    --pdf-serif-family "Times New Roman" \
    --pdf-sans-family "Arial" \
    --pdf-mono-family "Courier New" \
    --pdf-header-template "<table style='border-bottom: 1px solid black; width: 100%; margin-bottom: 1em'> \
      <tr> \
        <td style='text-align:left;'>_TITLE_</td> \
        <td style='text-align:left;'>_SECTION_</td> \
        <td style='text-align:right;'>_AUTHOR_</td> \
      </tr> \
    </table>" \
    --pdf-footer-template "<div style='width:100%; text-align:center; font-size:9pt;'> \
        Página _PAGENUM_ \
    </div>"
```
- Salida: [xproc-calibre.pdf](xslt/xproc-calibre.pdf)

---

### HTML+CSS → PDF con wkhtmltopdf

[Documentación wkhtmltopdf](https://wkhtmltopdf.org/usage/wkhtmltopdf.txt)

Este parece admitir más opciones que calibre, sin embargo no parece llevarse bien con los enlaces de las notas de pie que se generaron en la conversión de JATS-XML a HTML.

- Puede usar --print-media-type para usar CSS especial para impresión.
- Bastante potente con los headers y footers que pueden estar en un html a parte, soportando sustituciones de:
    - [page]       Replaced by the number of the pages currently being printed
    - [frompage]   Replaced by the number of the first page to be printed
    - [topage]     Replaced by the number of the last page to be printed
    - [webpage]    Replaced by the URL of the page being printed
    - [section]    Replaced by the name of the current section
    - [subsection] Replaced by the name of the current subsection
    - [date]       Replaced by the current date in system local format
    - [isodate]    Replaced by the current date in ISO 8601 extended format
    - [time]       Replaced by the current time in system local format
    - [title]      Replaced by the title of the of the current page object
    - [doctitle]   Replaced by the title of the output document
    - [sitepage]   Replaced by the number of the page in the current site being converted
    - [sitepages]  Replaced by the number of pages in the current site being converted

```ssh
wkhtmltopdf \
    --enable-local-file-access \
    --header-html xslt/wk-header.html \
    --footer-html xslt/wk-footer.html \
    --margin-bottom 20mm \
    --margin-top 20mm \
    xslt/xproc.html xslt/xproc-wk.pdf
```

```ssh
wkhtmltopdf \
    --enable-local-file-access \
    --header-html xslt/wk-header.html \
    --footer-html xslt/wk-footer.html \
    --margin-bottom 20mm \
    --margin-top 20mm \
    xslt/xproc-css.html xslt/xproc-css-wk.pdf
```

- Entrada: [Header](xslt/wk-header.html) y [Footer](xslt/wk-footer.html)
- Salida: [xproc-wk.pdf](xslt/xproc-wk.pdf)
- Salida: [xproc-css-wk.pdf](xslt/xproc-css-wk.pdf)

---
### HTML+CSS → PDF con vivliostyhle

- El más potente de las alternativas libres, permite además conversión a EPUB.
- Permite la creación de temas con gran variedad de ajustes que se pueden personalizar mediante ficheros de configuración.
- Composición de varios html en un único documento, usando una lista en `entry` del fichero de configuración.
- Aplicar diferentes estilos en cascada.
    - `--style:`:       Se trata como estilo de autor y sobrescribe estilos del HTML
    - `--user-style`:   Sobrescribe los del HTML pero no sbrescribe los de autor a menos que lleven !important
- Generar cubiertas automáticamente a partir de una imagen con `cover: 'cover.pnh'`.
- Generar tabla de contenido automáticamente con `toc: true`. Esto crea una página con el `title` del documento y otra página con el índice que incluirá todas aquellos elementos `h1`. Cada elemento será un enlace a la sección.
- También es psible crear un html con el contenido y los enlaces a las distintas secciones del documento principal con `toc: toc.html`.

#### Ejemplo de configuración: [vivliostyle.config.js](vivliostyle.config.js)

Aunque en el fichero se definan la entrada y output por defecto se puede especificar otros en la ejecución del comando, manteniendo el resto 

```js
module.exports = {
  entry: "xslt/xproc-css.html",
  output: "xslt/test-techbook.pdf",
  theme: "@vivliostyle/theme-techbook",

  // Opciones de impresión / PDF
  pdf: {
    format: "A4",
    baseFontSize: "10pt",       // tamaño de fuente por defecto
    pdfSerifFamily: "Times New Roman",  // tipografía para textos latinos
    pdfSansFamily: "Arial",
    pdfMonoFamily: "Courier New",
    marginTop: "2cm",
    marginBottom: "2cm",
    marginLeft: "2cm",
    marginRight: "2cm"
  },

  // Idioma y escritura
  lang: "es",                  // español
  direction: "ltr"             // escritura izquierda a derecha
};

```
- Con la configuración tal cual
    ```ssh
    vivliostyle build
    ```
- Especificando otra entrada y salida
    ```ssh
    vivliostyle build entrada.html -o output.pdf
    ```
- Técnicamente también es posible seleccionar el tema directamente sin necesidad de usar `vivliostyle.config.js` con la opción `--themee`

#### Ejemplos y temas usados
- Salida `@vivliostyle/theme-academic`: [xproc-css-vs-academic.pdf](xslt/xproc-css-vs-academic.pdf)
- Salida `@vivliostyle/theme-base`: [xproc-css-vs-base.pdf](xslt/xproc-css-vs-base.pdf)
- Salida `@vivliostyle/theme-techbook`: [xproc-css-vs-techbook.pdf](xslt/xproc-css-vs-techbook.pdf)
- Salida `@vivliostyle/theme-gutenberg`: [xproc-css-vs-gutenberg.pdf](xslt/xproc-css-vs-gutenberg.pdf)
- Salida `@vivliostyle/theme-gutenberg`: [xproc-vs-gutenberg.pdf](xslt/xproc-vs-gutenberg.pdf)
- Salida `@vivliostyle/theme-academic` con custom stylesheet: [xproc-css-vs-academic-custom.pdf](xslt/xproc-css-vs-academic-custom.pdf)


---

### Conversión a ebook

Además de la opción de `vivliostyle` tenemos la opción de `ebook-convert` a partir del HTML o PDF generados por cualquier otra herramienta.

Conversión desde HTML:
```
/opt/calibre/ebook-convert xslt/xproc-css.html  xslt/xproc-css-calibre.epub
```
- Salida [xproc-css-calibre.epub](xslt/xproc-css-calibre.epub)

Conversión desde PDF:
```
/opt/calibre/ebook-convert xslt/xproc-css-wk.pdf  xslt/xproc-css-wk-calibre.epub
```
- Salida [xproc-css-wk-calibre.epub](xslt/xproc-css-wk-calibre.epub)

---

