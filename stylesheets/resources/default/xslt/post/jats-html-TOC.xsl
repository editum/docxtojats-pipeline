<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:output method="html" indent="yes" encoding="UTF-8"/>

  <!-- Template raíz: el documento JATS -->
  <xsl:template match="/article">
    <html lang="en">
      <head>
        <meta charset="utf-8"/>
        <title>
          <xsl:value-of select="front/journal-meta/journal-title-group/journal-title"/>
          <xsl:text> — </xsl:text>
          <xsl:value-of select="front/article-meta/title-group/article-title"/>
        </title>
        <link href="publication.json" rel="publication" type="application/ld+json"/>
        <link href="style.css" rel="stylesheet" type="text/css"/>
      </head>
      <body>
        <h1>
          <xsl:value-of select="front/journal-meta/journal-title-group/journal-title"/>
        </h1>
        <h2>
          <xsl:value-of select="front/article-meta/title-group/article-title"/>
        </h2>

        <nav id="toc" role="doc-toc">
          <h2>Table of Contents</h2>
          <ol>
            <xsl:apply-templates select="body/sec"/>
          </ol>
        </nav>
      </body>
    </html>
  </xsl:template>

  <!-- Plantilla para cada sección -->
  <xsl:template match="sec">
    <li>
      <a href="document.html#{@id}">
        <xsl:choose>
          <xsl:when test="@id">
            <xsl:value-of select="normalize-space(title)"/>
          </xsl:when>
          <xsl:otherwise>
            <!-- Si no hay id, generamos uno -->
            <xsl:variable name="genid" select="concat('sec-', count(preceding::sec)+1)"/>
            <xsl:attribute name="href">
              <xsl:value-of select="concat('document.html#', $genid)"/>
            </xsl:attribute>
            <xsl:value-of select="normalize-space(title)"/>
          </xsl:otherwise>
        </xsl:choose>
      </a>

      <!-- Si hay subsecciones, listas anidadas -->
      <xsl:if test="sec">
        <ol>
          <xsl:apply-templates select="sec"/>
        </ol>
      </xsl:if>
    </li>
  </xsl:template>

</xsl:stylesheet>
