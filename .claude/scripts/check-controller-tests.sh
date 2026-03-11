#!/bin/bash
# Hook: PostToolUse — se ejecuta después de Edit o Write
# Advierte si se editó un controlador sin revisar cobertura de tests

input=$(cat)
file=$(echo "$input" | python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)
    path = d.get('tool_input', {}).get('file_path', '')
    print(path)
except:
    print('')
" 2>/dev/null)

if echo "$file" | grep -qE "app/Http/Controllers/.+\.php$"; then
    echo ""
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║  ⚠️  CONTROLADOR MODIFICADO — Verifica cobertura de tests    ║"
    echo "╠══════════════════════════════════════════════════════════════╣"
    echo "║  Por cada ruta en este controlador confirma que existan:     ║"
    echo "║                                                              ║"
    echo "║  ✓ Happy path por CADA ROL (admin, super-admin, employee)    ║"
    echo "║  ✓ Acceso denegado para roles que NO deben entrar            ║"
    echo "║  ✓ Validaciones y errores                                    ║"
    echo "║  ✓ Edge cases (cross-company, ya registrado, null, etc.)     ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo ""
fi
