<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="3.0" 
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xlink="http://www.w3.org/1999/xlink">

    <xsl:template match="fig">
        <figure>
            <xsl:if test="@id">
                <xsl:attribute name="id"><xsl:value-of select="@id"/></xsl:attribute>
            </xsl:if>

            <!-- APA: Label on its own line, bold -->
            <xsl:if test="label">
                <p class="fig-label"><strong><xsl:value-of select="label"/></strong></p>
            </xsl:if>

            <!-- APA: Caption title on next line, italic, strip leading dot/space -->
            <xsl:if test="caption/title">
                <p class="fig-caption">
                    <em>
                        <xsl:variable name="raw-title" select="normalize-space(caption/title)"/>
                        <xsl:choose>
                            <xsl:when test="starts-with($raw-title, '. ')">
                                <xsl:value-of select="substring($raw-title, 3)"/>
                            </xsl:when>
                            <xsl:when test="starts-with($raw-title, '.')">
                                <xsl:value-of select="substring($raw-title, 2)"/>
                            </xsl:when>
                            <xsl:otherwise>
                                <xsl:value-of select="$raw-title"/>
                            </xsl:otherwise>
                        </xsl:choose>
                    </em>
                </p>
            </xsl:if>

            <!-- Image -->
            <xsl:apply-templates select="graphic | media"/>

            <!-- APA: Only show notes if present in caption (not the title) -->
            <xsl:if test="caption/p">
                <div class="fig-foot">
                    <xsl:apply-templates select="caption/p"/>
                </div>
            </xsl:if>
        </figure>
    </xsl:template>

    <!-- Suppress default caption template for figures — we handle it explicitly -->
    <xsl:template match="fig/caption"/>
    <xsl:template match="fig/label"/>

    <xsl:template match="graphic">
        <img>
            <xsl:attribute name="src"><xsl:value-of select="@xlink:href"/></xsl:attribute>
            <xsl:if test="ancestor::fig/alt-text">
                <xsl:attribute name="alt"><xsl:value-of select="ancestor::fig/alt-text"/></xsl:attribute>
            </xsl:if>
        </img>
    </xsl:template>

    <xsl:template match="inline-graphic">
        <img class="inline">
            <xsl:attribute name="src"><xsl:value-of select="@xlink:href"/></xsl:attribute>
        </img>
    </xsl:template>

</xsl:stylesheet>
