<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="3.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <!-- JATS inline elements → HTML equivalents -->

    <xsl:template match="italic">
        <em><xsl:apply-templates/></em>
    </xsl:template>

    <xsl:template match="bold">
        <strong><xsl:apply-templates/></strong>
    </xsl:template>

    <xsl:template match="sub">
        <sub><xsl:apply-templates/></sub>
    </xsl:template>

    <xsl:template match="sup">
        <sup><xsl:apply-templates/></sup>
    </xsl:template>

    <xsl:template match="underline">
        <u><xsl:apply-templates/></u>
    </xsl:template>

    <xsl:template match="monospace">
        <code><xsl:apply-templates/></code>
    </xsl:template>

    <xsl:template match="sc">
        <span class="small-caps"><xsl:apply-templates/></span>
    </xsl:template>

    <xsl:template match="strike">
        <s><xsl:apply-templates/></s>
    </xsl:template>

</xsl:stylesheet>
