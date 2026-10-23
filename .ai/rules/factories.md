---
paths:
  - 'database/factories/**'
---

# Factories

## Factories use fake() helper and nested factory defaults
Factories call the fake() helper (not $this->faker), default state resolves parents via nested Model::factory() calls, and expose named states as methods (e.g. paid(), current()). Include @extends Factory<Model> PHPDoc.
