#!/usr/bin/env bash
# Sync wp-content/uploads to S3. Dry-run by default — pass --go to actually copy.
#
# Reads S3_BUCKET / S3_REGION / S3_PREFIX / AWS_* from .env in the repo root.
# Never deletes anything on either side.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/.env"
SRC="$ROOT/wp-content/uploads"

[ -f "$ENV_FILE" ] || { echo "ERROR: $ENV_FILE not found"; exit 1; }
[ -d "$SRC" ]      || { echo "ERROR: $SRC not found"; exit 1; }

# Pull only the keys we need, tolerating quotes and comments.
get_env() { grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"'"'"' \r' || true; }

S3_BUCKET="$(get_env S3_BUCKET)"
S3_REGION="$(get_env S3_REGION)"
S3_PREFIX="$(get_env S3_PREFIX)"
: "${S3_PREFIX:=wp-content/uploads}"

AWS_ACCESS_KEY_ID="$(get_env AWS_ACCESS_KEY_ID)"
AWS_SECRET_ACCESS_KEY="$(get_env AWS_SECRET_ACCESS_KEY)"
export AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION="$S3_REGION"

for v in S3_BUCKET S3_REGION AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY; do
  [ -n "${!v}" ] || { echo "ERROR: $v missing from .env"; exit 1; }
done

command -v aws >/dev/null || { echo "ERROR: aws CLI not installed (brew install awscli)"; exit 1; }

DEST="s3://${S3_BUCKET}/${S3_PREFIX%/}"

MODE=(--dryrun)
if [ "${1:-}" = "--go" ]; then MODE=(); fi

echo "Source : $SRC"
echo "Dest   : $DEST"
echo "Region : $S3_REGION"
[ ${#MODE[@]} -gt 0 ] && echo "MODE   : DRY RUN (pass --go to copy for real)" || echo "MODE   : LIVE COPY"
echo

aws s3 sync "$SRC" "$DEST" \
  ${MODE[@]+"${MODE[@]}"} \
  --only-show-errors \
  --no-progress \
  --exclude "*.DS_Store" \
  --exclude "cache/*" \
  --exclude "*/cache/*" \
  --exclude "litespeed/*" \
  --exclude "*.log" \
  --exclude "*.zip" --include "*/*.zip" \
  --cache-control "public, max-age=31536000, immutable"

echo
echo "Done. Verify a known file:"
echo "  aws s3 ls ${DEST}/2026/04/ | head"
