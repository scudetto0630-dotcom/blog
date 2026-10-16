#!/usr/bin/env python3
"""Scoped WordPress editor. Token is read from secure environment injection only."""
import argparse
import json
import os
import sys
import urllib.error
import urllib.request

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise urllib.error.HTTPError(req.full_url, code, 'Redirect rejected; credentials were not forwarded.', headers, fp)

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=['status', 'home', 'page', 'starter-posts', 'starter-status', 'starter-review', 'starter-refresh', 'managed-article', 'starter-image', 'plugin-update', 'site-audit', 'site-verification', 'introduction-review', 'introduction-refresh', 'cat-guide-reports', 'cat-guide-report-delete', 'cat-guide-maintenance', 'cat-guide-maintenance-run'])
    parser.add_argument('--payload', help='JSON file required for home/page writes')
    parser.add_argument('--query-route', action='store_true', help='Use WordPress rest_route query syntax')
    args = parser.parse_args()
    token = os.environ.get('HSH_EDIT_TOKEN')
    if not token:
        parser.error('HSH_EDIT_TOKEN is missing. Register it securely in environment settings; never paste it into chat.')
    if args.operation not in ('status', 'starter-status', 'starter-review', 'site-audit', 'introduction-review', 'cat-guide-reports', 'cat-guide-maintenance') and not args.payload:
        parser.error('--payload is required for writes')
    if args.operation in ('status', 'starter-status', 'starter-review', 'site-audit', 'introduction-review', 'cat-guide-reports', 'cat-guide-maintenance') and args.payload:
        parser.error('status does not accept a payload')
    data = None
    if args.payload:
        with open(args.payload, encoding='utf-8') as f:
            payload = json.load(f)
        if not isinstance(payload, dict):
            parser.error('Payload must be a JSON object')
        data = json.dumps(payload, ensure_ascii=False).encode()
    path = '/hirogaru/v1/' + ('starter-posts' if args.operation == 'starter-status' else 'starter-refresh' if args.operation == 'starter-review' else 'introduction' if args.operation in ('introduction-review', 'introduction-refresh') else 'cat-guide-maintenance' if args.operation == 'cat-guide-maintenance-run' else args.operation)
    url = 'https://hirogarushumi.com' + ('/?rest_route=' + path if args.query_route else '/wp-json' + path)
    request = urllib.request.Request(url, data=data, method='GET' if data is None else 'POST', headers={
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Cache-Control': 'no-cache',
    })
    try:
        with urllib.request.build_opener(NoRedirect()).open(request, timeout=120) as response:
            result = json.load(response)
            print(json.dumps(result, ensure_ascii=False, indent=2))
    except urllib.error.HTTPError as error:
        # Report a code, not headers or raw output that might include authentication data.
        print(f'WordPress returned HTTP {error.code}. No automatic retry was performed.', file=sys.stderr)
        return 1
    except (urllib.error.URLError, json.JSONDecodeError) as error:
        print('Connection or response failed. Check connectivity and confirm status before retrying a write.', file=sys.stderr)
        return 1
    return 0

if __name__ == '__main__':
    sys.exit(main())
