<xsl:stylesheet version="2.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <!-- identidad -->
  <xsl:template match="@*|node()">
    <xsl:copy>
      <xsl:apply-templates select="@*|node()" />
    </xsl:copy>
  </xsl:template>

  <!-- agrupar nodos de estilo consecutivos -->
  <xsl:template match="*[*[self::bold or self::italic or self::underline or self::monospace]]">
    <xsl:copy>
      <xsl:apply-templates select="@*"/>
      <xsl:for-each-group select="node()" 
                          group-adjacent="if (self::bold or self::italic or self::underline or self::monospace) 
                                          then name() 
                                          else ''">
        <xsl:choose>
          <!-- si son nodos de estilo consecutivos, los fusionamos -->
          <xsl:when test="self::bold or self::italic or self::underline or self::monospace">
            <xsl:element name="{name()}">
              <xsl:value-of select="current-group()" separator=" " />
            </xsl:element>
          </xsl:when>
          <!-- si no, copiamos normal -->
          <xsl:otherwise>
            <xsl:apply-templates select="current-group()" />
          </xsl:otherwise>
        </xsl:choose>
      </xsl:for-each-group>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>