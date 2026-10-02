#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 <hostname>" >&2
    exit 2
fi

host="$1"
generated_at="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"

echo "# HIMS SSL/TLS Report"
echo
echo "- Host: ${host}"
echo "- Generated (UTC): ${generated_at}"
echo
echo "## TLS 1.3 negotiation and certificate validation"
echo '```text'
curl --fail --silent --show-error --head --tlsv1.3 --tls-max 1.3 "https://${host}/up"
echo
openssl s_client -connect "${host}:443" -servername "${host}" -tls1_3 -verify_return_error </dev/null 2>&1 \
    | grep -E '^(New, TLS|Protocol *:|Cipher *:|Verification:|Verify return code:)'
echo '```'
echo
echo "## HTTP to HTTPS redirect"
echo '```text'
curl --silent --show-error --head "http://${host}/up"
echo '```'
echo
echo "## Certificate summary"
echo '```text'
openssl s_client -connect "${host}:443" -servername "${host}" -showcerts </dev/null 2>/dev/null \
    | openssl x509 -noout -subject -issuer -dates -fingerprint -sha256 -ext subjectAltName
echo '```'
