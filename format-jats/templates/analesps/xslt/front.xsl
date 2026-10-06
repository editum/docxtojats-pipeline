<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="3.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xlink="http://www.w3.org/1999/xlink">

    <xsl:template match="front">
        <header class="front">
            <!-- Inject dynamic PDF metadata block -->
            <xsl:call-template name="pdf-calculated-vars" />

            <!-- Cabecera Gris -->
            <div class="analesps-header">
                <!-- Columna Izquierda -->
                <div class="header-left">
                    <img src="./logo.png" alt="Journal Logo"
                        class="journal-logo" />
                    <div class="journal-info">
                        <p class="journal-info-title">
                            <xsl:value-of
                                select="lower-case(journal-meta/journal-title-group/journal-title[@xml:lang='en'])" />
                        </p>
                        <p class="journal-info-details">
                            <xsl:value-of select="article-meta/pub-date/year[1]" />
                            <xsl:text>, vol. </xsl:text>
                            <xsl:value-of select="article-meta/volume" />
                            <xsl:text>, nº </xsl:text>
                            <xsl:value-of select="article-meta/issue" />
                            <xsl:text> (may-august), </xsl:text>
                            <xsl:value-of select="article-meta/fpage" />
                            <xsl:text>-</xsl:text>
                            <xsl:value-of select="article-meta/lpage" />
                        </p>
                        <p class="journal-info-doi">
                            <xsl:variable name="doi"
                                select="article-meta/article-id[@pub-id-type='doi']" />
                            <a href="https://doi.org/{$doi}">https://doi.org/<xsl:value-of
                                    select="$doi" /></a>
                        </p>
                    </div>
                </div>

                <!-- Columna Centro -->
                <div class="header-center">
                    <p>Published by <xsl:value-of select="journal-meta/publisher/publisher-name" /></p>
                    <p>in <a href="https://revistas.um.es/analesps">https://revistas.um.es/analesps</a></p>
                    <xsl:choose>
                        <xsl:when test="article-meta/permissions/copyright-year">
                            <p>© Copyright <xsl:value-of
                                    select="article-meta/permissions/copyright-year" />: The
    author(s).</p>
                        </xsl:when>
                        <xsl:otherwise>
                            <p>© Copyright <xsl:value-of select="article-meta/pub-date/year[1]" />:
    The author(s).</p>
                        </xsl:otherwise>
                    </xsl:choose>
                    <p>ISSN online: <xsl:value-of select="journal-meta/issn" /></p>
                </div>

                <!-- Columna Derecha -->
                <div class="header-right">
                    <span>Creative Commons</span>
                    <span>4.0: BY</span>
                    <img src="./ccby.png" alt="CC BY 4.0" class="cc-logo" />
                </div>
            </div>

            <!-- Título -->
            <div class="title-group">
                <h1>
                    <xsl:apply-templates select="article-meta/title-group/article-title/node()" />
                </h1>
                <xsl:if test="article-meta/title-group/subtitle">
                    <h2>
                        <xsl:apply-templates select="article-meta/title-group/subtitle/node()" />
                    </h2>
                </xsl:if>
            </div>

            <!-- Autores en Línea -->
            <div class="analesps-authors-block">
                <xsl:for-each select="article-meta/contrib-group/contrib[@contrib-type='author']">
                    <div class="analesps-author">
                        <xsl:value-of select="name/given-names" />
                        <xsl:text> </xsl:text>
                        <xsl:value-of select="name/surname" />

                        <!-- Sup elements for affiliations -->
                        <sup>
                            <xsl:for-each select="xref[@ref-type='aff']">
                                <xsl:variable name="inst-name" select="string(//aff[@id=current()/@rid]/institution)"/>
                                <xsl:for-each-group select="//aff" group-by="institution">
                                    <xsl:if test="institution = $inst-name">
                                        <xsl:value-of select="position()"/>
                                    </xsl:if>
                                </xsl:for-each-group>
                                <xsl:if test="position() != last()">, </xsl:if>
                            </xsl:for-each>
                            <xsl:if test="@corresp='yes'">,*</xsl:if>
                        </sup>
                    </div>
                    <xsl:if
                        test="position() != last()">
                        <xsl:choose>
                            <xsl:when test="position() = last() - 1">, and </xsl:when>
                            <xsl:otherwise>, </xsl:otherwise>
                        </xsl:choose>
                    </xsl:if>
                </xsl:for-each>
            </div>

            <!-- Filiaciones -->
            <div class="analesps-affiliations-block">
                <xsl:for-each-group select="article-meta/aff" group-by="institution">
                    <span class="analesps-affiliation">
                        <sup><xsl:value-of select="position()"/></sup>
                        <xsl:text> </xsl:text>
                        <xsl:value-of select="institution" />
                        <xsl:if test="country">
                            <xsl:text>, </xsl:text>
                            <xsl:value-of select="country" />
                        </xsl:if>
                    </span>
                </xsl:for-each-group>
            </div>

            <!-- Resumen en 2 columnas -->
            <div class="analesps-abstracts-container">
                <div class="analesps-abstracts-grid">

                    <!-- Columna Izquierda: Español -->
                    <div class="analesps-abstract-col">
                        <!-- Título Español -->
                        <xsl:if
                            test="article-meta/title-group/trans-title-group[@xml:lang='es']/trans-title">
                            <p>
                                <span class="analesps-meta-label">Título: </span>
                                <xsl:apply-templates
                                    select="article-meta/title-group/trans-title-group[@xml:lang='es']/trans-title/node()" />
                            </p>
                        </xsl:if>

                        <!-- Abstract Español -->
                        <xsl:if test="article-meta/trans-abstract[@xml:lang='es']">
                            <p>
                                <span class="analesps-meta-label">Resumen: </span>
                                <xsl:apply-templates
                                    select="article-meta/trans-abstract[@xml:lang='es']/p/node() | article-meta/trans-abstract[@xml:lang='es']/node()[not(self::p)]" />
                            </p>
                        </xsl:if>

                        <!-- Keywords Español -->
                        <xsl:if test="article-meta/kwd-group[@xml:lang='es']">
                            <p>
                                <span class="analesps-meta-label">Palabras clave: </span>
                                <xsl:for-each select="article-meta/kwd-group[@xml:lang='es']/kwd">
                                    <xsl:apply-templates select="node()" />
                                    <xsl:if
                                        test="position() != last()">; </xsl:if>
                                </xsl:for-each>
                                <xsl:text>.</xsl:text>
                            </p>
                        </xsl:if>
                    </div>

                    <!-- Columna Derecha: Inglés -->
                    <div class="analesps-abstract-col">
                        <!-- Abstract Inglés -->
                        <xsl:if test="article-meta/abstract">
                            <p>
                                <span class="analesps-meta-label">Abstract: </span>
                                <xsl:apply-templates
                                    select="article-meta/abstract/p/node() | article-meta/abstract/node()[not(self::p)]" />
                            </p>
                        </xsl:if>

                        <!-- Keywords Inglés -->
                        <xsl:if
                            test="article-meta/kwd-group[@xml:lang='en'] | article-meta/kwd-group[not(@xml:lang)]">
                            <p>
                                <span class="analesps-meta-label">Keywords: </span>
                                <xsl:for-each
                                    select="(article-meta/kwd-group[@xml:lang='en'] | article-meta/kwd-group[not(@xml:lang)])/kwd">
                                    <xsl:apply-templates select="node()" />
                                    <xsl:if
                                        test="position() != last()">; </xsl:if>
                                </xsl:for-each>
                                <xsl:text>.</xsl:text>
                            </p>
                        </xsl:if>
                    </div>

                </div>
            </div>

            <!-- Caja Correspondencia -->
            <xsl:variable name="corresp-author"
                select="article-meta/contrib-group/contrib[@corresp='yes']" />
            <xsl:if test="$corresp-author">
                <div class="analesps-corresp-box">
                    <p>
                        <span class="analesps-meta-label">* Correspondence address [Dirección para
    correspondencia]:</span>
                        <br />
                        <xsl:value-of select="$corresp-author/name/given-names" />
                        <xsl:text> </xsl:text>
                        <xsl:value-of select="$corresp-author/name/surname" />
                        <xsl:text>. E-mail: </xsl:text>
                        <a href="mailto:{$corresp-author/email}">
                            <xsl:value-of select="$corresp-author/email" />
                        </a>
                    </p>
                </div>
            </xsl:if>

        </header>
    </xsl:template>

</xsl:stylesheet>