Lee en este orden:
1. CLAUDE.md
2. ai-specs/specs/base-standards.mdc
3. ai-specs/specs/backend-standards.mdc
4. ai-specs/specs/domain-model.md
5. ai-specs/specs/data-model.md

Adopta el rol de ai-specs/.agents/backend-developer.md.

Si existe `.claude/doc/$ARGUMENTS/backend.md`, léelo y úsalo como plan.
Si no existe, crea el plan en `.claude/doc/$ARGUMENTS/backend.md` con el checklist completo del agente, luego espera aprobación del usuario antes de continuar.

Al implementar, sigue el orden de backend-standards.mdc:
migración → modelo+relaciones+casts → factory+seeder → action(s) → form request → controller → ruta → wayfinder:generate → npm run build → feature tests → pint → ejecutar tests
