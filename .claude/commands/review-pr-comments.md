Revisa los comentarios de Copilot en el PR: $ARGUMENTS

1. Usa la herramienta `gh` para obtener los comentarios del PR:
   - `gh pr view $ARGUMENTS --comments` para ver comentarios generales
   - `gh api repos/cristiancastano852/mango-app/pulls/{PR_NUMBER}/comments` para review comments en el código

2. Para cada comentario de Copilot:
   - Lee el archivo y la línea específica mencionada en el contexto de esta aplicación
   - Evalúa críticamente si el comentario aplica dado el stack (Laravel 12, Inertia Vue 3, multi-tenant con company_id, dominio en app/Domain/)
   - Ignora sugerencias genéricas que contradigan las convenciones del proyecto (CLAUDE.md)
   - Prioriza: seguridad, correctitud, N+1 queries, validaciones faltantes, edge cases

3. Agrupa los comentarios en:
   - **Aplicar**: cambios que claramente mejoran el código en este contexto
   - **Ignorar**: sugerencias genéricas que no aplican o contradicen las convenciones del proyecto
   - **Discutir**: sugerencias válidas pero con trade-offs

4. Aplica los cambios del grupo "Aplicar" uno por uno, siguiendo las convenciones del proyecto.

5. Corre los tests afectados con `php artisan test --compact` después de cada cambio.

6. Si modificaste PHP, corre `vendor/bin/pint --dirty --format agent` al final.
