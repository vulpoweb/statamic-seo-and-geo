# Security Policy

## Supported versions

The latest minor release receives security fixes.

## Reporting a vulnerability

Email **security@vulpo.be** rather than opening a public issue. Include the affected version, what an attacker can do, and a reproduction if you have one. You will get an acknowledgement within three working days.

Please do not test against sites you do not own.

## Scope notes

The addon serves public text routes (`/robots.txt`, `/llms.txt`, `/sitemap.xml`) built from control panel settings, and logs 404s and AI crawler visits including the requesting path and referer. Reports about data reachable through those routes, about the control panel screens honouring the `view`/`edit vulpo seo` permissions, or about redirect rules being used to reach somewhere unintended are all in scope.
