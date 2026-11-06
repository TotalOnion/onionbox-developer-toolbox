# What is it?

CLI toolbox for WP devs

## Help

`wp help onionbox`

### Redirection Audit

Audit http redirects from the Redirection plugin to check for 404's, loops etc

```
  wp onionbox redirection-audit

  [--export-only]
    Just export the redirects from Redirection. Don't run the audit. All flags other than --module are irrelevant

  [--module=<wordpress|apache|nginx|all>]
    Which module to test. Defaults to 'all'

  [--max-redirects=<count>]
    How many redirects to follow before giving up. Defaults to 5.

  [--max-age=<days>]
    How many days since a redirect was hit is considered "old". Defaults to 365

  [--verbose]
    Show passes as well as failures, and extra info in general.

  [--ids=<id>...]
    Array of redirect IDs to test. Useful for retesting a subset from an earlier full audit
  
  [--id-from=<id>]
    Start at <id> and continue
 
  [--id-to=<id>]
   End at <id>

  [--match-url=<url>]
    Check a single match-url. Copy and paste this into quotes from the Redirection page in wp-admin
```

### ld+json audit & validator

Checks URLs for valid ld+json Structured data

This currently uses the latest schema RDF from schema.org, saved to src/Validators/LdJson:
https://github.com/schemaorg/schemaorg/blob/main/data/releases/30.0/schemaorg-current-https.ttl

```
  wp onionbox ldjson 

  [--target-post-types=<post-type>...]
    Test all of a post type. Pass in a csv to do multiple

  [--target-path=<path>]
    Test a specific path

  [--target-ids=<ids>...]
    Test a specific post ID. Pass in a csv for multiple.

  [--follow-links]
    Follow things like image links to see if they are resolving correctly

  [--verbose]
    Show passes as well as failures, and extra info in general.

  [--vverbose]
    Dump out the ld+json etc. Only use this if you are testing individual posts, or you really like large log files
```
### Sitemap audit

Checks that `sitemaps.xml` and every sub-sitemap are valid, and that every URL in them gives a 200.

- **Sitemaps**: must give a 200, be `text/xml` or `application/xml`, be well-formed XML with a `<sitemapindex>` or `<urlset>` root in the sitemaps.org namespace, have a full URL in every `<loc>`, valid `<lastmod>` dates, and stay within 50,000 entries / 50MB.
- **URLs** are requested without following redirects:
  - 3xx: a warning if a Redirection rule explains it (the sitemap should list the target), bad if not.
  - 401/403: bad, a private page is listed as public.
  - 404/410: bad, a routing problem.
  - 5xx: bad, a server error.
- **Hidden and suppressed markets** (WPML hidden languages, plus markets suppressed in onion-seopress-helper / pr-seopress-helper) must not appear anywhere in the sitemaps. Suppressed markets with their helper plugin inactive are bad.
- URLs on the live domain are rewritten to the Lando domain, so a local run never requests the live site. URLs on other domains are flagged and not requested.

Exits with code 1 if anything is bad, so it can fail a pipeline. With `--format=json` the output has `stats`, a `summary` count per kind of problem, and an `issues` list.

```
  wp onionbox sitemap

  [--format=<stdout|json>]
    The output format of the command. Defaults to stdout

  [--sitemap=<root_sitemap_filename>]
    Which sitemap file to start at. Defaults to sitemaps.xml

  [--expected-post-types=<post-type>...]
    Warn about published, indexable posts of <post-type> (outside hidden markets) that are not in the sitemaps. Pass in a csv for multiple.

  [--check-links]
    Also check that every href, src, srcset (and lazy-load data-src/data-srcset) link on each page that points at this site gives a 200. Each link is checked once. Slow.

  [--staggered]
    This site uses staggered delivery: fail if no seopress-helper plugin is active, warn if no markets are suppressed.

  [--verbose]
    Show passes as well as failures, and extra info in general.

  [--vverbose]
    Also print every request. Useful for debugging
```
