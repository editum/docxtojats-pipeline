<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="3.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xlink="http://www.w3.org/1999/xlink">

    <!-- Global Variable for Main Language -->
    <xsl:variable name="main-lang">
        <xsl:choose>
            <xsl:when test="/article/@xml:lang"><xsl:value-of select="/article/@xml:lang"/></xsl:when>
            <xsl:otherwise>en</xsl:otherwise>
        </xsl:choose>
    </xsl:variable>

    <!-- Dictionary Function/Template -->
    <xsl:template name="get-label">
        <xsl:param name="lang"/>
        <xsl:param name="type"/>
        <xsl:choose>
            <xsl:when test="$type = 'Title'">
                <xsl:choose>
                    <xsl:when test="$lang = 'es'">Título</xsl:when>
                    <xsl:when test="$lang = 'pt'">Título</xsl:when>
                    <xsl:when test="$lang = 'fr'">Titre</xsl:when>
                    <xsl:otherwise>Title</xsl:otherwise>
                </xsl:choose>
            </xsl:when>
            <xsl:when test="$type = 'Abstract'">
                <xsl:choose>
                    <xsl:when test="$lang = 'es'">Resumen</xsl:when>
                    <xsl:when test="$lang = 'pt'">Resumo</xsl:when>
                    <xsl:when test="$lang = 'fr'">Résumé</xsl:when>
                    <xsl:otherwise>Abstract</xsl:otherwise>
                </xsl:choose>
            </xsl:when>
            <xsl:when test="$type = 'Keywords'">
                <xsl:choose>
                    <xsl:when test="$lang = 'es'">Palabras clave</xsl:when>
                    <xsl:when test="$lang = 'pt'">Palavras-chave</xsl:when>
                    <xsl:when test="$lang = 'fr'">Mots-clés</xsl:when>
                    <xsl:otherwise>Keywords</xsl:otherwise>
                </xsl:choose>
            </xsl:when>
        </xsl:choose>
    </xsl:template>

    <xsl:template match="front">
        <header class="front">
            <!-- Inject dynamic PDF metadata block -->
            <xsl:call-template name="pdf-calculated-vars"/>
            
            <!-- Inject Header for PDF -->
            <div class="pdf-header">
                <div class="header-title">
                    <xsl:value-of select="article-meta/title-group/article-title"/>
                </div>
                <div class="header-page"></div>
            </div>

            <xsl:apply-templates select="article-meta"/>

            <!-- Inject Footer for PDF -->
            <div class="pdf-footer">
                <div class="footer-journal">
                    <xsl:value-of select="journal-meta/journal-title-group/journal-title"/>
                    <xsl:text>, vol. </xsl:text>
                    <xsl:value-of select="article-meta/volume"/>
                    <xsl:text> (</xsl:text>
                    <xsl:value-of select="article-meta/pub-date/year[1]"/>
                    <xsl:text>)</xsl:text>
                </div>
                <div class="footer-doi">
                    <xsl:variable name="doi" select="article-meta/article-id[@pub-id-type='doi']"/>
                    <a href="https://doi.org/{$doi}">https://doi.org/<xsl:value-of select="$doi"/></a>
                </div>
            </div>
        </header>
    </xsl:template>

    <xsl:template match="article-meta">
        <div class="article-meta">
            <!-- 1. Logo -->
            <xsl:apply-templates select="self-uri[@specific-use='issue-cover']"/>
            
            <!-- 2. Main Title -->
            <div class="title-group">
                <h1><xsl:apply-templates select="title-group/article-title/node()"/></h1>
                <xsl:if test="title-group/subtitle">
                    <h2><xsl:apply-templates select="title-group/subtitle/node()"/></h2>
                </xsl:if>
            </div>
            
            <!-- 3. Authors -->
            <xsl:apply-templates select="contrib-group"/>
            
            <!-- 3.5 Affiliations (Deduplicated) -->
            <xsl:if test="//aff">
                <div class="affiliations-list" style="margin-top: 1em; font-style: italic;">
                    <xsl:for-each-group select="//aff" group-by="institution">
                        <div class="affiliation">
                            <sup><xsl:value-of select="position()"/></sup>
                            <xsl:text> </xsl:text>
                            <xsl:value-of select="institution"/>
                            <xsl:if test="country">
                                <xsl:text>, </xsl:text>
                                <xsl:value-of select="country"/>
                            </xsl:if>
                        </div>
                    </xsl:for-each-group>
                </div>
            </xsl:if>
            
            <!-- 4. Main Abstract & Keywords Block -->
            <div class="meta-block" lang="{$main-lang}">
                <!-- Main Abstract -->
                <xsl:if test="abstract">
                    <p>
                        <span class="meta-label">
                            <xsl:call-template name="get-label">
                                <xsl:with-param name="lang" select="$main-lang"/>
                                <xsl:with-param name="type" select="'Abstract'"/>
                            </xsl:call-template>
                            <xsl:text>: </xsl:text>
                        </span>
                        <!-- Apply templates to children of p inside abstract, avoiding the <p> wrapper -->
                        <xsl:apply-templates select="abstract/p/node() | abstract/node()[not(self::p)]"/>
                    </p>
                </xsl:if>
                
                <!-- Main Keywords -->
                <xsl:if test="kwd-group[@xml:lang=$main-lang] | kwd-group[not(@xml:lang)]">
                    <p>
                        <span class="meta-label">
                            <xsl:call-template name="get-label">
                                <xsl:with-param name="lang" select="$main-lang"/>
                                <xsl:with-param name="type" select="'Keywords'"/>
                            </xsl:call-template>
                            <xsl:text>: </xsl:text>
                        </span>
                        <xsl:for-each select="(kwd-group[@xml:lang=$main-lang] | kwd-group[not(@xml:lang)])/kwd">
                            <xsl:apply-templates select="node()"/>
                            <xsl:if test="position() != last()">; </xsl:if>
                        </xsl:for-each>
                        <xsl:text>.</xsl:text>
                    </p>
                </xsl:if>
            </div>

            <!-- 5. Translated Blocks -->
            <xsl:variable name="meta" select="."/>
            <xsl:for-each-group select="trans-abstract | title-group/trans-title-group | kwd-group[not(@xml:lang=$main-lang)]" group-by="@xml:lang">
                <xsl:variable name="lang" select="current-grouping-key()"/>
                <xsl:if test="$lang and $lang != $main-lang">
                    <div class="meta-block" lang="{$lang}">
                        <!-- Title -->
                        <xsl:if test="$meta/title-group/trans-title-group[@xml:lang=$lang]">
                            <p>
                                <span class="meta-label">
                                    <xsl:call-template name="get-label">
                                        <xsl:with-param name="lang" select="$lang"/>
                                        <xsl:with-param name="type" select="'Title'"/>
                                    </xsl:call-template>
                                    <xsl:text>: </xsl:text>
                                </span>
                                <span class="trans-title">
                                    <xsl:apply-templates select="$meta/title-group/trans-title-group[@xml:lang=$lang]/trans-title/node()"/>
                                </span>
                            </p>
                        </xsl:if>
                        
                        <!-- Abstract -->
                        <xsl:if test="$meta/trans-abstract[@xml:lang=$lang]">
                            <p>
                                <span class="meta-label">
                                    <xsl:call-template name="get-label">
                                        <xsl:with-param name="lang" select="$lang"/>
                                        <xsl:with-param name="type" select="'Abstract'"/>
                                    </xsl:call-template>
                                    <xsl:text>: </xsl:text>
                                </span>
                                <xsl:apply-templates select="$meta/trans-abstract[@xml:lang=$lang]/p/node() | $meta/trans-abstract[@xml:lang=$lang]/node()[not(self::p)]"/>
                            </p>
                        </xsl:if>

                        <!-- Keywords -->
                        <xsl:if test="$meta/kwd-group[@xml:lang=$lang]">
                            <p>
                                <span class="meta-label">
                                    <xsl:call-template name="get-label">
                                        <xsl:with-param name="lang" select="$lang"/>
                                        <xsl:with-param name="type" select="'Keywords'"/>
                                    </xsl:call-template>
                                    <xsl:text>: </xsl:text>
                                </span>
                                <xsl:for-each select="$meta/kwd-group[@xml:lang=$lang]/kwd">
                                    <xsl:apply-templates select="node()"/>
                                    <xsl:if test="position() != last()">; </xsl:if>
                                </xsl:for-each>
                                <xsl:text>.</xsl:text>
                            </p>
                        </xsl:if>
                    </div>
                </xsl:if>
            </xsl:for-each-group>

            <!-- 6. Copyright -->
            <xsl:if test="permissions/license/license-p | permissions/copyright-statement">
                <div class="copyright-block">
                    <p>
                        <span class="meta-label">Copyright: </span>
                        <xsl:choose>
                            <xsl:when test="permissions/copyright-statement">
                                <xsl:apply-templates select="permissions/copyright-statement/node()"/>
                            </xsl:when>
                            <xsl:otherwise>
                                <xsl:apply-templates select="permissions/license/license-p/node()"/>
                            </xsl:otherwise>
                        </xsl:choose>
                    </p>
                </div>
            </xsl:if>

            <!-- 7. History / Datos de edición -->
            <xsl:if test="history/date">
                <div class="history-block">
                    <p>
                        <span class="meta-label">Datos de edición: </span>
                        <xsl:for-each select="history/date">
                            <xsl:choose>
                                <xsl:when test="@date-type = 'received'">Recibido</xsl:when>
                                <xsl:when test="@date-type = 'accepted'">Aceptado</xsl:when>
                                <xsl:when test="@date-type = 'rev-recd'">Revisado</xsl:when>
                                <xsl:when test="@date-type = 'pub'">Publicado</xsl:when>
                                <xsl:otherwise><xsl:value-of select="@date-type"/></xsl:otherwise>
                            </xsl:choose>
                            <xsl:text>: </xsl:text>
                            <xsl:value-of select="day"/>
                            <xsl:text>-</xsl:text>
                            <xsl:value-of select="month"/>
                            <xsl:text>-</xsl:text>
                            <xsl:value-of select="year"/>
                            <xsl:if test="position() != last()">; </xsl:if>
                        </xsl:for-each>
                        <xsl:text>.</xsl:text>
                    </p>
                </div>
            </xsl:if>
            
        </div>
    </xsl:template>

    <xsl:template match="self-uri[@specific-use='issue-cover']">
        <div class="issue-cover">
            <img src="{@xlink:href}" alt="Issue Cover"/>
        </div>
    </xsl:template>

    <xsl:template match="contrib-group">
        <div class="contrib-group">
            <xsl:apply-templates/>
        </div>
    </xsl:template>

    <xsl:template match="contrib">
        <div class="contrib">
            <xsl:choose>
                <xsl:when test="email">
                    <a href="mailto:{email}" class="author-email-link">
                        <xsl:apply-templates select="name"/>
                    </a>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:apply-templates select="name"/>
                </xsl:otherwise>
            </xsl:choose>
            <!-- Render affiliations as superscripts based on deduplicated institution index -->
            <xsl:if test="xref[@ref-type='aff']">
                <sup>
                    <xsl:for-each select="xref[@ref-type='aff']">
                        <xsl:variable name="inst-name" select="string(//aff[@id=current()/@rid]/institution)"/>
                        <xsl:for-each-group select="//aff" group-by="institution">
                            <xsl:if test="institution = $inst-name">
                                <xsl:value-of select="position()"/>
                            </xsl:if>
                        </xsl:for-each-group>
                        <xsl:if test="position() != last()">
                            <xsl:text>, </xsl:text>
                        </xsl:if>
                    </xsl:for-each>
                </sup>
            </xsl:if>
            <xsl:apply-templates select="*[not(self::name) and not(self::email) and not(self::xref[@ref-type='aff'])]"/>
        </div>
    </xsl:template>

    <xsl:template match="name">
        <span class="author-name">
            <xsl:value-of select="given-names"/>
            <xsl:text> </xsl:text>
            <xsl:value-of select="surname"/>
        </span>
    </xsl:template>

    <xsl:template match="contrib-id[@contrib-id-type='orcid']">
        <div class="orcid">
            <a href="{.}" target="_blank" rel="noopener noreferrer">
                <img src="./orcid.png" alt="ORCID Logo" class="orcid-logo"/>
                <xsl:apply-templates/>
            </a>
        </div>
    </xsl:template>

    <!-- Dynamic Calculated PDF Variables -->
    <xsl:template name="pdf-calculated-vars">
        <div id="pdf-calculated-vars" style="display: none;">
            <!-- #one-line-authors# -->
            <div data-var="one-line-authors">
                <xsl:for-each select="//contrib[@contrib-type='author']">
                    <xsl:value-of select="name/given-names"/>
                    <xsl:text> </xsl:text>
                    <xsl:value-of select="name/surname"/>
                    <xsl:if test="position() != last()">, </xsl:if>
                </xsl:for-each>
            </div>
            <!-- #one-line-authors-compact# -->
            <div data-var="one-line-authors-compact">
                <xsl:variable name="authors" select="//contrib[@contrib-type='author']"/>
                <xsl:choose>
                    <xsl:when test="count($authors) &gt; 1">
                        <xsl:value-of select="$authors[1]/name/given-names"/>
                        <xsl:text> </xsl:text>
                        <xsl:value-of select="$authors[1]/name/surname"/>
                        <xsl:text> et al.</xsl:text>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="$authors[1]/name/given-names"/>
                        <xsl:text> </xsl:text>
                        <xsl:value-of select="$authors[1]/name/surname"/>
                    </xsl:otherwise>
                </xsl:choose>
            </div>
        </div>
    </xsl:template>

</xsl:stylesheet>

