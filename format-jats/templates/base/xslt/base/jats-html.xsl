<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="3.0" 
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xlink="http://www.w3.org/1999/xlink"
    xmlns:mml="http://www.w3.org/1998/Math/MathML"
    exclude-result-prefixes="xlink">

    <xsl:output method="html" indent="yes" encoding="UTF-8" omit-xml-declaration="yes"/>

    <!-- Default identity transform to prevent swallowing content -->
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="/">
        <xsl:apply-templates select="article"/>
    </xsl:template>

    <xsl:template match="article">
        <article>
            <xsl:apply-templates select="front"/>
            <xsl:apply-templates select="body"/>
            <xsl:apply-templates select="back"/>
        </article>
    </xsl:template>

</xsl:stylesheet>
