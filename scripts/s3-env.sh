#!/usr/bin/env bash
# Source this to get AWS credentials from .env into your shell:
#   source scripts/s3-env.sh
# Then plain `aws` commands work:
#   aws s3 ls s3://$S3_BUCKET --recursive --summarize | tail -3
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")/.." && pwd)"
_ge() { grep -E "^$1=" "$ROOT/.env" 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"'"'"' \r' || true; }
export AWS_ACCESS_KEY_ID="$(_ge AWS_ACCESS_KEY_ID)"
export AWS_SECRET_ACCESS_KEY="$(_ge AWS_SECRET_ACCESS_KEY)"
export AWS_DEFAULT_REGION="$(_ge S3_REGION)"
export S3_BUCKET="$(_ge S3_BUCKET)"
unset -f _ge
echo "AWS env loaded: bucket=$S3_BUCKET region=$AWS_DEFAULT_REGION"
