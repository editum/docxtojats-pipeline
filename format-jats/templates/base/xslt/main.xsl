<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="3.0" 
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xlink="http://www.w3.org/1999/xlink"
    xmlns:mml="http://www.w3.org/1998/Math/MathML"
    exclude-result-prefixes="xlink mml">

    <!-- Import base template -->
    <xsl:import href="base/jats-html.xsl"/>

    <!-- Include overrides -->
    <xsl:include href="overrides/front.xsl"/>
    <xsl:include href="overrides/body.xsl"/>
    <xsl:include href="overrides/refs.xsl"/>
    <xsl:include href="overrides/figures.xsl"/>
    <xsl:include href="overrides/tables.xsl"/>
    <xsl:include href="overrides/back.xsl"/>
    <xsl:include href="overrides/inline.xsl"/>

</xsl:stylesheet>
