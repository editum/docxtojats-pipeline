<?xml version="1.0" encoding="UTF-8"?>

<p:declare-step xmlns:p="http://www.w3.org/ns/xproc"
                xmlns:c="http://www.w3.org/ns/xproc-step"
                xmlns:cx="http://xmlcalabash.com/ns/extensions"
                version="1.0">

   <p:input port="source"/>
   <p:input port="parameters" kind="parameter"/>

   <p:output port="html"/>
   <p:serialization port="html" method="html" encoding="utf-8"/>


   <p:xslt name="format-APA-citations" version="2.0">
      <!-- format citations in APA format -->
      <p:input port="stylesheet">
         <p:document href="../../xslt/citations-prep/jats-APAcit.xsl"/>
      </p:input>
   </p:xslt>

   <p:xslt name="display-html" version="2.0">
      <!-- convert into HTML for display -->
      <p:with-param name="transform" select="'jats-APAcit-html.xpl'"/>
      <p:input port="stylesheet">
         <p:document href="../../xslt/main/jats-html.xsl"/>
      </p:input>
   </p:xslt>


   

   <p:xslt name="insert-toc" version="2.0">
      <!-- Paso 3: Insert TOC en el HTML generado -->
      <p:input port="stylesheet">
         <p:document href="../../xslt/util/generate-toc.xsl"/>
      </p:input>
   </p:xslt>



   <p:xslt name="display-html" version="2.0">
      <!-- convert into HTML for display -->
      <p:with-param name="transform" select="'jats-APAcit-html.xpl'"/>
      <p:input port="stylesheet">
         <p:document href="../../xslt/main/jats-html.xsl"/>
      </p:input>
   </p:xslt>

   <p:xslt name="toc-html" version="2.0">
      <!-- Insert TOC to the HTML-->

      
   </p:xslt>




   <!-- Step 1: Generate TOC-->
   <p:xslt name="make-toc">
      <p:input port="source"></p:input>
   </p:xslt>





   <p:input port="source"/>

   <p:input port="parameters" kind="parameter"/>

   <p:output port="result"/>

   <p:serialization port="result" method="html" encoding="us-ascii"/>

   <p:xslt name="format-APA-citations" version="2.0">
      <!-- format citations in APA format -->
      <p:input port="stylesheet">
         <p:document href="../../xslt/citations-prep/jats-APAcit.xsl"/>
      </p:input>
   </p:xslt>

   <p:xslt name="display-html" version="2.0">
      <!-- convert into HTML for display -->
      <p:with-param name="transform" select="'jats-APAcit-html.xpl'"/>
      <p:input port="stylesheet">
         <p:document href="../../xslt/main/jats-html.xsl"/>
      </p:input>
   </p:xslt>

</p:declare-step>
