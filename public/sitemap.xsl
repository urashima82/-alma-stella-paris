<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:xhtml="http://www.w3.org/1999/xhtml">

    <xsl:output method="html" encoding="UTF-8" indent="yes"/>

    <xsl:template match="/">
        <html lang="en">
        <head>
            <meta charset="UTF-8"/>
            <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
            <title>Sitemap — Alma Stella Paris</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: #FAF8F4;
                    color: #2C2418;
                    padding: 2rem;
                    line-height: 1.6;
                }
                h1 {
                    font-size: 1.5rem;
                    font-weight: 600;
                    margin-bottom: 0.25rem;
                }
                .subtitle {
                    color: #7a7060;
                    font-size: 0.875rem;
                    margin-bottom: 1.5rem;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    background: #fff;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
                }
                th {
                    background: #F0EBE1;
                    text-align: left;
                    padding: 0.75rem 1rem;
                    font-size: 0.75rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    color: #5a5040;
                }
                td {
                    padding: 0.5rem 1rem;
                    font-size: 0.85rem;
                    border-bottom: 1px solid #F0EBE1;
                }
                tr:last-child td { border-bottom: none; }
                tr:hover td { background: #FAF8F4; }
                a {
                    color: #C9A84C;
                    text-decoration: none;
                    word-break: break-all;
                }
                a:hover { text-decoration: underline; }
                .priority { text-align: center; }
                .freq { text-align: center; }
            </style>
        </head>
        <body>
            <h1>Sitemap — Alma Stella Paris</h1>
            <p class="subtitle">
                <xsl:value-of select="count(sitemap:urlset/sitemap:url)"/> URLs
            </p>
            <table>
                <thead>
                    <tr>
                        <th>URL</th>
                        <th class="priority">Priority</th>
                        <th class="freq">Change freq.</th>
                        <th>Last modified</th>
                    </tr>
                </thead>
                <tbody>
                    <xsl:for-each select="sitemap:urlset/sitemap:url">
                        <xsl:sort select="sitemap:priority" order="descending" data-type="number"/>
                        <tr>
                            <td>
                                <a href="{sitemap:loc}">
                                    <xsl:value-of select="sitemap:loc"/>
                                </a>
                            </td>
                            <td class="priority">
                                <xsl:value-of select="sitemap:priority"/>
                            </td>
                            <td class="freq">
                                <xsl:value-of select="sitemap:changefreq"/>
                            </td>
                            <td>
                                <xsl:value-of select="sitemap:lastmod"/>
                            </td>
                        </tr>
                    </xsl:for-each>
                </tbody>
            </table>
        </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
