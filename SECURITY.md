# Security policy

Where this file is silent, the [laranail security policy](https://github.com/laranail/.github/blob/HEAD/SECURITY.md) applies.

## Reporting a vulnerability

Report security issues privately to **security@simtabi.com**. Do not open a public issue.

Include the affected version, the steps to reproduce, and the impact you believe it has. You will
get an acknowledgement within three business days and an assessment within ten.

This package is part of the authkit family and sits on the authentication path. A vulnerability
here may also affect `laranail/authkit`; say so in your report if you believe it does, and we will
coordinate the fix across the family rather than patching one package in isolation.

> **Prefer GitHub private vulnerability reporting** when you can: open it from this
> repository's Security tab. The report arrives attached to the repo with a draft advisory
> and a CVE request path already in place. Email is the fallback for anyone who would
> rather not use GitHub.

## Supported versions

The `main` line receives security fixes. Pre-1.0 releases are not separately maintained.
