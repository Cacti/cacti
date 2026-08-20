# Package import MIME validation

Local package uploads are inspected from the server-controlled temporary file
before their bytes enter the session. Cacti uses Symfony Mime to ask `fileinfo`
for a content-derived type and accepts only the ZIP, XML, and gzip aliases used by
supported packages. Browser-provided filenames and `$_FILES['type']` do not
participate in the decision.

The gate fails closed when `fileinfo` is unavailable or detection is
inconclusive. Operators then receive an actionable import error and a security
log entry identifies the missing or disabled extension without disclosing a
temporary path.

MIME validation is defense in depth. Package signatures, decompression limits,
archive containment and XML parser protections remain authoritative and must not
be removed when additional upload sites adopt the detector.
