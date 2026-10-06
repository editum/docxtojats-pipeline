<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="3.0" 
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xlink="http://www.w3.org/1999/xlink">

    <xsl:template match="ref-list">
        <section class="references">
            <xsl:if test="@id">
                <xsl:attribute name="id"><xsl:value-of select="@id"/></xsl:attribute>
            </xsl:if>
            <h2>References</h2>
            <ul>
                <xsl:apply-templates select="ref"/>
            </ul>
        </section>
    </xsl:template>

    <xsl:template match="ref">
        <li class="reference">
            <xsl:if test="@id">
                <xsl:attribute name="id"><xsl:value-of select="@id"/></xsl:attribute>
            </xsl:if>
            <xsl:choose>
                <!-- Prefer element-citation for structured APA rendering -->
                <xsl:when test="element-citation">
                    <xsl:apply-templates select="element-citation" mode="apa"/>
                </xsl:when>
                <!-- Fallback to mixed-citation if no element-citation exists -->
                <xsl:when test="mixed-citation">
                    <div class="citation"><xsl:apply-templates select="mixed-citation/node()"/></div>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:apply-templates/>
                </xsl:otherwise>
            </xsl:choose>
        </li>
    </xsl:template>

    <!-- Suppress mixed-citation when element-citation is also present -->
    <xsl:template match="mixed-citation[../element-citation]"/>

    <!-- ============================================ -->
    <!-- APA rendering from element-citation          -->
    <!-- ============================================ -->

    <!-- Journal article -->
    <xsl:template match="element-citation[@publication-type='journal']" mode="apa">
        <div class="citation">
            <!-- Authors -->
            <xsl:call-template name="render-authors">
                <xsl:with-param name="group" select="person-group[@person-group-type='author']"/>
            </xsl:call-template>

            <!-- (Year). -->
            <xsl:if test="year">
                <xsl:text> (</xsl:text>
                <xsl:value-of select="year"/>
                <xsl:text>). </xsl:text>
            </xsl:if>

            <!-- Article title. -->
            <xsl:if test="article-title">
                <xsl:value-of select="article-title"/>
                <xsl:text>. </xsl:text>
            </xsl:if>

            <!-- Journal name in italic -->
            <xsl:if test="source">
                <em><xsl:value-of select="source"/></em>
            </xsl:if>

            <!-- , volume(issue), pages -->
            <xsl:if test="volume">
                <xsl:text>, </xsl:text>
                <em><xsl:value-of select="volume"/></em>
            </xsl:if>
            <xsl:if test="issue">
                <xsl:text>(</xsl:text>
                <xsl:value-of select="issue"/>
                <xsl:text>)</xsl:text>
            </xsl:if>
            <xsl:if test="fpage | page-range">
                <xsl:text>, </xsl:text>
                <xsl:choose>
                    <xsl:when test="page-range">
                        <xsl:value-of select="page-range"/>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="fpage"/>
                        <xsl:if test="lpage">
                            <xsl:text>–</xsl:text>
                            <xsl:value-of select="lpage"/>
                        </xsl:if>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:if>
            <xsl:text>. </xsl:text>

            <!-- DOI link -->
            <xsl:call-template name="render-doi"/>
        </div>
    </xsl:template>

    <!-- Book -->
    <xsl:template match="element-citation[@publication-type='book']" mode="apa">
        <div class="citation">
            <!-- Authors -->
            <xsl:call-template name="render-authors">
                <xsl:with-param name="group" select="person-group[@person-group-type='author']"/>
            </xsl:call-template>

            <!-- (Year). -->
            <xsl:if test="year">
                <xsl:text> (</xsl:text>
                <xsl:value-of select="year"/>
                <xsl:text>). </xsl:text>
            </xsl:if>

            <!-- Book title in italic -->
            <xsl:if test="source">
                <em><xsl:value-of select="source"/></em>
                <xsl:text>. </xsl:text>
            </xsl:if>

            <!-- Publisher -->
            <xsl:if test="publisher-name">
                <xsl:value-of select="publisher-name"/>
                <xsl:text>.</xsl:text>
            </xsl:if>

            <!-- DOI link if any -->
            <xsl:call-template name="render-doi"/>
        </div>
    </xsl:template>

    <!-- Book chapter -->
    <xsl:template match="element-citation[@publication-type='chapter']" mode="apa">
        <div class="citation">
            <!-- Authors -->
            <xsl:call-template name="render-authors">
                <xsl:with-param name="group" select="person-group[@person-group-type='author']"/>
            </xsl:call-template>

            <!-- (Year). -->
            <xsl:if test="year">
                <xsl:text> (</xsl:text>
                <xsl:value-of select="year"/>
                <xsl:text>). </xsl:text>
            </xsl:if>

            <!-- Chapter title. -->
            <xsl:if test="article-title | chapter-title">
                <xsl:value-of select="article-title | chapter-title"/>
                <xsl:text>. </xsl:text>
            </xsl:if>

            <!-- In Editors (Eds.), -->
            <xsl:if test="person-group[@person-group-type='editor']">
                <xsl:text>In </xsl:text>
                <xsl:call-template name="render-editors">
                    <xsl:with-param name="group" select="person-group[@person-group-type='editor']"/>
                </xsl:call-template>
                <xsl:text>, </xsl:text>
            </xsl:if>

            <!-- Book title in italic -->
            <xsl:if test="source">
                <em><xsl:value-of select="source"/></em>
                <!-- Pages -->
                <xsl:if test="fpage | page-range">
                    <xsl:text> (pp. </xsl:text>
                    <xsl:choose>
                        <xsl:when test="page-range"><xsl:value-of select="page-range"/></xsl:when>
                        <xsl:otherwise>
                            <xsl:value-of select="fpage"/>
                            <xsl:if test="lpage">–<xsl:value-of select="lpage"/></xsl:if>
                        </xsl:otherwise>
                    </xsl:choose>
                    <xsl:text>)</xsl:text>
                </xsl:if>
                <xsl:text>. </xsl:text>
            </xsl:if>

            <!-- Publisher -->
            <xsl:if test="publisher-name">
                <xsl:value-of select="publisher-name"/>
                <xsl:text>.</xsl:text>
            </xsl:if>

            <!-- DOI -->
            <xsl:call-template name="render-doi"/>
        </div>
    </xsl:template>

    <!-- Fallback for other publication types (web, report, thesis, etc.) -->
    <xsl:template match="element-citation" mode="apa">
        <div class="citation">
            <!-- Authors -->
            <xsl:if test="person-group[@person-group-type='author']">
                <xsl:call-template name="render-authors">
                    <xsl:with-param name="group" select="person-group[@person-group-type='author']"/>
                </xsl:call-template>
            </xsl:if>

            <!-- (Year). -->
            <xsl:if test="year">
                <xsl:text> (</xsl:text>
                <xsl:value-of select="year"/>
                <xsl:text>). </xsl:text>
            </xsl:if>

            <!-- Title -->
            <xsl:if test="article-title">
                <xsl:value-of select="article-title"/>
                <xsl:text>. </xsl:text>
            </xsl:if>

            <!-- Source in italic -->
            <xsl:if test="source">
                <em><xsl:value-of select="source"/></em>
                <xsl:text>. </xsl:text>
            </xsl:if>

            <!-- Publisher -->
            <xsl:if test="publisher-name">
                <xsl:value-of select="publisher-name"/>
                <xsl:text>. </xsl:text>
            </xsl:if>

            <!-- DOI -->
            <xsl:call-template name="render-doi"/>
        </div>
    </xsl:template>

    <!-- ============================================ -->
    <!-- Helper templates                             -->
    <!-- ============================================ -->

    <!-- Render author list: Surname, I., Surname2, I2., & Surname3, I3. -->
    <xsl:template name="render-authors">
        <xsl:param name="group"/>
        <xsl:for-each select="$group/name">
            <xsl:value-of select="surname"/>
            <xsl:text>, </xsl:text>
            <xsl:value-of select="given-names"/>
            <xsl:choose>
                <xsl:when test="position() = last()"/>
                <xsl:when test="position() = last() - 1">
                    <xsl:text>, &amp; </xsl:text>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:text>, </xsl:text>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:for-each>
    </xsl:template>

    <!-- Render editor list for book chapters: I. Surname (Ed.) or I. Surname & I2. Surname2 (Eds.) -->
    <xsl:template name="render-editors">
        <xsl:param name="group"/>
        <xsl:for-each select="$group/name">
            <xsl:value-of select="given-names"/>
            <xsl:text> </xsl:text>
            <xsl:value-of select="surname"/>
            <xsl:choose>
                <xsl:when test="position() = last()"/>
                <xsl:when test="position() = last() - 1">
                    <xsl:text>, &amp; </xsl:text>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:text>, </xsl:text>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:for-each>
        <xsl:choose>
            <xsl:when test="count($group/name) > 1"> (Eds.)</xsl:when>
            <xsl:otherwise> (Ed.)</xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Render DOI as clickable link -->
    <xsl:template name="render-doi">
        <xsl:if test="pub-id[@pub-id-type='doi']">
            <xsl:text> </xsl:text>
            <xsl:variable name="doi" select="pub-id[@pub-id-type='doi']"/>
            <a href="https://doi.org/{$doi}" target="_blank" rel="noopener noreferrer">
                <xsl:text>https://doi.org/</xsl:text>
                <xsl:value-of select="$doi"/>
            </a>
        </xsl:if>
    </xsl:template>

    <!-- ext-link (also used in body text) -->
    <xsl:template match="ext-link">
        <a href="{@xlink:href}" target="_blank" rel="noopener noreferrer" class="ext-link">
            <xsl:choose>
                <xsl:when test="normalize-space(.) != ''">
                    <xsl:apply-templates/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="@xlink:href"/>
                </xsl:otherwise>
            </xsl:choose>
        </a>
    </xsl:template>

</xsl:stylesheet>
