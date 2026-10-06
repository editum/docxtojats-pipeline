<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="3.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:template match="table-wrap">
        <section class="table-wrap">
            <xsl:if test="@id">
                <xsl:attribute name="id"><xsl:value-of select="@id"/></xsl:attribute>
            </xsl:if>

            <!-- APA: Label on its own line, bold -->
            <xsl:if test="label">
                <p class="table-label"><strong><xsl:value-of select="label"/></strong></p>
            </xsl:if>

            <!-- APA: Caption title on next line, italic, strip leading dot/space -->
            <xsl:if test="caption/title">
                <p class="table-caption">
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

            <!-- Table content -->
            <div class="table-responsive">
                <xsl:apply-templates select="table"/>
            </div>

            <!-- APA: Only show table-wrap-foot for Notes, not repeat label/caption -->
            <xsl:if test="table-wrap-foot">
                <div class="table-foot">
                    <xsl:apply-templates select="table-wrap-foot"/>
                </div>
            </xsl:if>
        </section>
    </xsl:template>

    <xsl:template match="table-wrap-foot">
        <xsl:apply-templates/>
    </xsl:template>

    <xsl:template match="table">
        <table>
            <xsl:apply-templates/>
        </table>
    </xsl:template>

    <xsl:template match="thead | tbody | tr | td | th">
        <xsl:copy>
            <xsl:copy-of select="@*"/>
            <xsl:apply-templates/>
        </xsl:copy>
    </xsl:template>

</xsl:stylesheet>
