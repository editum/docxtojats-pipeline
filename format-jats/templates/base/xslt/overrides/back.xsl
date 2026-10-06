<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="3.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:template match="back">
        <footer class="back">
            <xsl:apply-templates select="*[not(local-name()='ref-list')]"/>
            
            <xsl:if test="//fn">
                <section class="notes">
                    <h2>
                        <xsl:choose>
                            <xsl:when test="/article/@xml:lang = 'es'">Notas</xsl:when>
                            <xsl:otherwise>Notes</xsl:otherwise>
                        </xsl:choose>
                    </h2>
                    <div class="notes-list">
                        <xsl:apply-templates select="//fn" mode="notes-section"/>
                    </div>
                </section>
            </xsl:if>

            <xsl:apply-templates select="ref-list"/>
        </footer>
    </xsl:template>

    <xsl:template match="ack">
        <section class="acknowledgements">
            <h2>Acknowledgements</h2>
            <xsl:apply-templates/>
        </section>
    </xsl:template>

    <xsl:template match="fn-group">
        <!-- Ignorado porque las notas se extraen globalmente en la sección Notes -->
    </xsl:template>

    <xsl:template match="fn">
        <sup class="fn-link">
            <a href="#{@id}">
                <xsl:value-of select="label"/>
            </a>
        </sup>
    </xsl:template>

    <xsl:template match="fn" mode="notes-section">
        <div class="note" id="{@id}">
            <sup><xsl:value-of select="label"/></sup>
            <xsl:text> </xsl:text>
            <xsl:apply-templates select="*[not(self::label)]"/>
        </div>
    </xsl:template>

</xsl:stylesheet>
