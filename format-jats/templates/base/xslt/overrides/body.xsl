<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="3.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:template match="body">
        <main class="body">
            <xsl:apply-templates/>
        </main>
    </xsl:template>

    <xsl:template match="sec">
        <section>
            <xsl:if test="@id">
                <xsl:attribute name="id"><xsl:value-of select="@id"/></xsl:attribute>
            </xsl:if>
            <xsl:apply-templates/>
        </section>
    </xsl:template>

    <xsl:template match="sec/title">
        <xsl:variable name="level" select="count(ancestor::sec) + 1"/>
        <xsl:element name="h{$level}">
            <xsl:apply-templates/>
        </xsl:element>
    </xsl:template>

    <xsl:template match="p[count(node()[not(self::text()[normalize-space()=''])]) = 1 and xref[@ref-type='fig' or @ref-type='table']]">
        <!-- Ignorar párrafos que solo contienen un enlace a figura o tabla (ej. para lens elife) -->
    </xsl:template>

    <xsl:template match="p">
        <p><xsl:apply-templates/></p>
    </xsl:template>

    <xsl:template match="list">
        <xsl:choose>
            <xsl:when test="@list-type='order' or @list-type='number'">
                <ol><xsl:apply-templates/></ol>
            </xsl:when>
            <xsl:otherwise>
                <ul><xsl:apply-templates/></ul>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template match="list-item">
        <li>
            <xsl:if test="@id">
                <xsl:attribute name="id"><xsl:value-of select="@id"/></xsl:attribute>
            </xsl:if>
            <xsl:apply-templates/>
        </li>
    </xsl:template>

    <xsl:template match="xref">
        <a href="#{@rid}">
            <xsl:apply-templates/>
        </a>
    </xsl:template>

    <xsl:template match="mml:math" xmlns:mml="http://www.w3.org/1998/Math/MathML">
        <xsl:copy-of select="."/>
    </xsl:template>

    <xsl:template match="text()[not(ancestor::ext-link) and contains(., 'http')]">
        <xsl:analyze-string select="." regex="https?://[^\s()&lt;&gt;]+">
            <xsl:matching-substring>
                <a href="{.}" target="_blank" rel="noopener noreferrer"><xsl:value-of select="."/></a>
            </xsl:matching-substring>
            <xsl:non-matching-substring>
                <xsl:value-of select="."/>
            </xsl:non-matching-substring>
        </xsl:analyze-string>
    </xsl:template>

</xsl:stylesheet>
