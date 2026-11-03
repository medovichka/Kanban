set -eu

cd "$(dirname "$0")/.."

fail=0
for file in $(find src bin public config tests instrumentation db -name '*.php' | sort); do
    if ! php -l "$file" > /dev/null 2>&1; then
        php -l "$file"
        fail=1
    fi
done

[ "$fail" -eq 0 ] && echo "PHP: синтаксис в порядке"
exit "$fail"
