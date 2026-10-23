---
paths:
  - 'app/Models/**'
---

# Models

## Models declare fillable/hidden via PHP attributes and casts() method
User, Team and all domain models declare attributes with the #[Fillable([...])]/#[Hidden([...])] PHP attributes (not $fillable/$guarded properties) and define casts() instead of $casts. Write new models the same way.
