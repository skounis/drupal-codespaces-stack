<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
                xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
                xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <xsl:output method="html" encoding="UTF-8" indent="yes"/>

  <!-- Root template -->
  <xsl:template match="/">
    <html lang="[langcode]">
      <head>
        <title>[title]</title>
        <script type="text/javascript" src="[jquery]"/>
        <script type="text/javascript" src="[jquery-tablesorter]"/>
        <script type="text/javascript" src="[parser-date-iso8601]"/>
        <script type="text/javascript" src="[xsl-js]"/>
        <link href="[xsl-css]" type="text/css" rel="stylesheet"/>
      </head>
      <body>
        <header role="banner">
          <h1>[title]</h1>
        </header>
        <main role="main">
          <xsl:choose>
            <xsl:when test="//sitemap:url">
              <xsl:call-template name="sitemapTable"/>
            </xsl:when>
            <xsl:otherwise>
              <xsl:call-template name="sitemapIndexTable"/>
            </xsl:otherwise>
          </xsl:choose>
        </main>
        <footer role="contentinfo" id="footer">
          <p>[generated-by]</p>
        </footer>
      </body>
    </html>
  </xsl:template>

  <!-- sitemapIndexTable template -->
  <xsl:template name="sitemapIndexTable">
    <div id="information">
      <p>[number-of-sitemaps]:
        <xsl:value-of select="count(sitemap:sitemapindex/sitemap:sitemap)"/>
      </p>
    </div>
    <table class="sitemap index">
      <thead>
        <tr>
          <th>[sitemap-url]</th>
          <th>[lastmod]</th>
        </tr>
      </thead>
      <tbody>
        <xsl:apply-templates select="sitemap:sitemapindex/sitemap:sitemap"/>
      </tbody>
    </table>
  </xsl:template>

  <!-- sitemapTable template -->
  <xsl:template name="sitemapTable">
    <div id="information">
      <p>[number-of-urls]:
        <xsl:value-of select="count(sitemap:urlset/sitemap:url)"/>
      </p>
    </div>
    <table class="sitemap">
      <thead>
        <tr>
          <th>[url-location]</th>
          <th>[lastmod]</th>
          <th>[changefreq]</th>
          <th>[priority]</th>
          <!-- Show this header only if xhtml:link elements are present -->
          <xsl:if test="sitemap:urlset/sitemap:url/xhtml:link">
            <th>[translation-set]</th>
          </xsl:if>
          <!-- Show this header only if image:image elements are present -->
          <xsl:if test="sitemap:urlset/sitemap:url/image:image">
            <th>[images]</th>
          </xsl:if>
        </tr>
      </thead>
      <tbody>
        <xsl:apply-templates select="sitemap:urlset/sitemap:url"/>
      </tbody>
    </table>
  </xsl:template>

  <!-- sitemap:sitemap template -->
  <xsl:template match="sitemap:sitemap">
    <tr>
      <td>
        <xsl:variable name="sitemap_location">
          <xsl:value-of select="sitemap:loc"/>
        </xsl:variable>
        <a href="{$sitemap_location}">
          <xsl:value-of select="$sitemap_location"/>
        </a>
      </td>
      <td>
        <xsl:value-of select="sitemap:lastmod"/>
      </td>
    </tr>
  </xsl:template>

  <!-- sitemap:url template -->
  <xsl:template match="sitemap:url">
    <tr>
      <td>
        <xsl:variable name="url_location">
          <xsl:value-of select="sitemap:loc"/>
        </xsl:variable>
        <a href="{$url_location}">
          <xsl:value-of select="$url_location"/>
        </a>
      </td>
      <td>
        <xsl:value-of select="sitemap:lastmod"/>
      </td>
      <td>
        <xsl:value-of select="sitemap:changefreq"/>
      </td>
      <td>
        <xsl:choose>
          <!-- If priority is not defined, show the default value of 0.5 -->
          <xsl:when test="sitemap:priority">
            <xsl:value-of select="sitemap:priority"/>
          </xsl:when>
          <xsl:otherwise>0.5</xsl:otherwise>
        </xsl:choose>
      </td>
      <!-- Show this column only if xhtml:link elements are present -->
      <xsl:if test="/sitemap:urlset/sitemap:url/xhtml:link">
        <td>
          <xsl:if test="xhtml:link">
            <ul class="translation-set">
              <xsl:apply-templates select="xhtml:link"/>
            </ul>
          </xsl:if>
        </td>
      </xsl:if>
      <!-- Show this column only if image:image elements are present -->
      <xsl:if test="/sitemap:urlset/sitemap:url/image:image">
        <td>
          <xsl:if test="image:image">
            <ul class="images">
              <xsl:apply-templates select="image:image"/>
            </ul>
          </xsl:if>
        </td>
      </xsl:if>
    </tr>
  </xsl:template>

  <!-- xhtml:link template -->
  <xsl:template match="xhtml:link">
    <xsl:variable name="url_location">
      <xsl:value-of select="@href"/>
    </xsl:variable>
    <li>
      <span>
        <xsl:value-of select="@hreflang"/>
      </span>
      <a href="{$url_location}">
        <xsl:value-of select="$url_location"/>
      </a>
    </li>
  </xsl:template>

  <!-- image:image template -->
  <xsl:template match="image:image">
    <xsl:variable name="image_location">
      <xsl:value-of select="image:loc"/>
    </xsl:variable>
    <xsl:variable name="image_title">
      <xsl:value-of select="image:title"/>
    </xsl:variable>
    <li>
      <a href="{$image_location}" title="{$image_title}">
        <xsl:choose>
          <xsl:when test="image:caption">
            <xsl:value-of select="image:caption"/>
          </xsl:when>
          <xsl:otherwise>
            <xsl:value-of select="$image_location"/>
          </xsl:otherwise>
        </xsl:choose>
      </a>
    </li>
  </xsl:template>

</xsl:stylesheet>
