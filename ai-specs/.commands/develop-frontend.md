Lee en este orden:
1. CLAUDE.md
2. ai-specs/specs/frontend-standards.mdc

Adopta el rol de ai-specs/.agents/frontend-developer.md.

Si existe `.claude/doc/$ARGUMENTS/frontend.md`, léelo y úsalo como plan.
Si no existe, crea el plan en `.claude/doc/$ARGUMENTS/frontend.md` con el checklist completo del agente, luego espera aprobación del usuario antes de continuar.

Al implementar, sigue el orden de frontend-standards.mdc:
revisar components/ui/ → crear/modificar página Vue → Wayfinder imports → Tailwind → i18n → AppSidebar si aplica → npm run build → verificar en browser
