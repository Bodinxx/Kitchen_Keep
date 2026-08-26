# Kitchen_Keep
**RECIPE WEBSITE**

**Requirements & Functional Specification**
**MySQL-Backed Recipe Platform**

<img width="512" height="342" alt="Recipe_Website_Requirements_Specification" src="https://github.com/user-attachments/assets/39ad785c-4e53-4df0-a79e-cefaa52ffcd8" />



# Document Control

| **Item** | **Value** |
|----|----|
| Document | Recipe Website --- Requirements & Functional Specification |
| Version | 2.0 |
| Date | August 26, 2026 |
| Status | Requirements baseline — MySQL edition |
| Primary hosting assumption | WHC.ca PHP-capable shared hosting with MySQL 5.7+ |
| Primary storage model | MySQL relational database (InnoDB, utf8mb4) |
| Restore model | mysqldump backup; manual restore through hosting control panel |

## Terminology

| **Term** | **Meaning** |
|----|----|
| Recipe owner | The registered account that created and owns a recipe. |
| User | A normal verified registered account. |
| Editor | A content-moderation role with expanded recipe, image, tag and ingredient permissions. |
| Administrator | The single highest-privilege role responsible for configuration, users, editors, backups and site maintenance. |
| Authoritative data | Data that cannot be recreated from other site records, such as recipes, users, ratings and cookbooks. |
| Derived data | Generated data such as search indexes and cached rating averages that can be rebuilt. |
| Soft deleted | Removed from normal use but retained for administrative review until purged. |
| Cookbook | A user-owned collection of recipe references with sections and per-recipe scaling preferences. |

# 1. Executive Summary

The Recipe Website will be a publicly accessible recipe-discovery and cookbook platform. Anyone may browse published recipes. Verified registered users may contribute recipes, rate recipes, create cookbooks, and participate in moderation through flagging. Editors moderate content and maintain shared taxonomies; one Administrator manages the site, users, editors, configuration, backups and maintenance.

All persistent application data is stored in a MySQL database (InnoDB, utf8mb4). Structured JSON columns are used for compound recipe attributes such as ingredients, steps, tags and categories, while uploaded images remain ordinary files. MySQL's native indexing and FULLTEXT search replace the previously planned JSON index files, improving performance, reliability, and data integrity.

The initial release includes structured ingredients, automatic serving/yield scaling, metric and US measurement conversion, ingredient-aware volume-to-weight conversions where reliable density data exists, ratings, recipe PDF/print export, private shareable cookbooks, guided recipe creation, reverse moderation, responsive design, four themes, accessibility targets, SEO structured data, and a required What Can I Make? ingredient-matching feature.

# 2. Goals, Scope and Constraints

## 2.1 Primary Goals
- Provide a clean public recipe library that is easy to browse on desktop, tablet and phone.
- Allow verified users to contribute recipes without pre-approval while retaining effective reverse moderation.
- Store all persistent application data in a MySQL database to ensure data integrity, ACID transactions, and reliable concurrent access.
- Support reliable scaling and unit conversion through structured ingredient data rather than free-form ingredient strings.
- Allow users to create private cookbooks and optionally share them through static public links.
- Provide useful recipe discovery, including ingredient inclusion/exclusion and What Can I Make?.
- Keep administration practical for a small site team consisting of one Administrator and multiple Editors.

## 2.2 Explicit Constraints
- MySQL 5.7 or later is required; InnoDB engine and utf8mb4 charset are mandatory.
- Initial hosting must be compatible with normal PHP-capable WHC.ca shared hosting with a MySQL database.
- Automatic in-application site restoration is out of scope; restores are manual via the hosting control panel.
- Full recipe revision history is out of scope to control storage growth.
- Cookbook duplication is out of scope.
- Persistent personal pantry inventory is out of scope for launch.
- Automatic nutrition calculation is out of scope.
- Administrator impersonation of users is prohibited.

# 3. Architecture and Storage Model

## 3.1 Database-Backed Persistence

All persistent application data is stored in a MySQL database. The connection is configured in `config/db.php` (not committed — copy `config/db.example.php` and fill in your credentials). The schema is in `database/schema.sql` and must be imported into the MySQL database before first use.

**Tables and their purpose:**

| Table | Description |
|----|---|
| `users` | Registered accounts; indexed by `email` and `username`. |
| `recipes` | Recipe records; compound fields (`ingredients`, `steps`, `tags`, `categories`, `images`) stored as JSON columns; FULLTEXT index on `title` and `description`. |
| `cookbooks` | User-owned collections of recipe references; `sections` stored as a JSON column. |
| `ratings` | One row per user per recipe; composite primary key `(recipe_id, user_id)`. |
| `ingredients` | Canonical ingredient dictionary; `aliases` stored as JSON; FULLTEXT index on `name`. |
| `moderation_flags` | User-submitted content flags; resolved by editors. |
| `audit_log` | Immutable append-only record of administrative actions. |
| `site_config` | Key–value pairs for runtime site settings; seeded with defaults on first install. |

**Setting up the database:**
1. Create the MySQL database and user on your host.
2. Import the schema: `mysql -u <user> -p <dbname> < database/schema.sql`
3. Copy `config/db.example.php` to `config/db.php` and enter your credentials.
4. `config/db.php` is listed in `.gitignore` and must never be committed.

- **ARCH-001 --- MySQL as single source of truth** *(Launch)* All authoritative records (recipes, users, ratings, cookbooks, ingredients) are stored in MySQL with appropriate indexes and constraints.
- **ARCH-002 --- JSON columns for compound data** *(Launch)* Compound fields that do not require individual column querying (ingredient lists, step arrays, tag arrays) are stored as MySQL JSON columns, keeping the schema flat and queries simple.
- **ARCH-003 --- Per-recipe ratings** *(Launch)* Ratings are stored in the `ratings` table with a composite primary key `(recipe_id, user_id)` preventing duplicates at the database level.
- **ARCH-004 --- Ingredient dictionary** *(Launch)* The `ingredients` table with a FULLTEXT index on `name` provides fast autocomplete and canonical lookup.
- **ARCH-005 --- Native MySQL indexes** *(Launch)* MySQL FULLTEXT, B-tree, and composite indexes replace the previously planned JSON index files. No external search indexes need to be rebuilt.
- **ARCH-006 --- Protected credentials** *(Launch)* Database credentials are stored in `config/db.php` outside the public web root and must not be committed to version control. Uploaded media remains in the `public/uploads/` directory.

## 3.2 Authoritative Data

All data below lives in MySQL and is the single authoritative record.

| **Table** | **Description** |
|----|---|
| `recipes` | Full recipe content |
| `users` | Account credentials and profile |
| `ratings` | Per-user recipe votes |
| `cookbooks` | User cookbook definitions |
| `ingredients` | Ingredient dictionary |
| `moderation_flags` | Content flag records |
| `audit_log` | Administrative audit trail |
| `site_config` | Runtime site configuration |

# 4. Users, Authentication and Profiles

## 4.1 Registration

- **USR-001 --- Open registration** *(Launch)* Anyone may register, subject to CAPTCHA and email verification.
- **USR-002 --- Required registration fields** *(Launch)* Registration requires a unique username, real name, email address and password.
- **USR-003 --- Private real name** *(Launch)* Real name is mandatory for administrative identification but must never be displayed publicly.
- **USR-004 --- Email verification** *(Launch)* A new account is not fully active until the supplied email address has been verified.
- **USR-005 --- Password storage** *(Launch)* Passwords must be securely hashed and never stored in plain text.
- **USR-006 --- Password minimum** *(Launch)* Passwords must be at least 8 characters; arbitrary uppercase/symbol composition rules are not required.
- **USR-007 --- Remember Me** *(Launch)* Login may provide a secure Remember Me option for trusted devices without storing the password.
- **USR-008 --- Password recovery** *(Launch)* Forgot Password must use a time-limited, single-use email reset link and should avoid unnecessarily confirming whether an unauthenticated email exists.
- **USR-009 --- Email changes** *(Launch)* A replacement email becomes active only after the new address is verified.
- **USR-010 --- Username changes** *(Launch)* Users may change usernames subject to a reasonable cooldown. Internal relationships use immutable user IDs.

## 4.2 Profiles
- **PRO-001 --- Public profile** *(Launch)* Each registered account has a public contributor profile showing username, optional avatar, optional About Me, published recipe count and published recipes.
- **PRO-002 --- Private profile data** *(Launch)* Real name, email, joined date and security information remain private.
- **PRO-003 --- Avatar choices** *(Launch)* Users may upload a custom avatar or choose a provided/generated default avatar.
- **PRO-004 --- Profile recipe discovery** *(Launch)* Visitors may search, filter, sort and paginate a contributor's published recipes using normal recipe tools.

# 5. Roles and Permissions

| **Capability**                     | **User** | **Editor**    | **Administrator** |
|------------------------------------|----------|---------------|-------------------|
| Browse public recipes              | Yes      | Yes           | Yes               |
| Add/edit own recipes               | Yes      | Yes           | Yes               |
| Edit any recipe                    | No       | Yes           | Yes               |
| Rate/flag recipes                  | Yes      | Yes           | Yes               |
| Moderate recipes/images            | No       | Yes           | Yes               |
| Manage ingredients/tags/categories | No       | Yes           | Yes               |
| See user real names                | No       | Yes           | Yes               |
| See user email addresses           | No       | No by default | Yes               |
| Suspend users                      | No       | Yes           | Yes               |
| Promote/demote Editors             | No       | No            | Yes               |
| Manage site settings/backups       | No       | No            | Yes               |
| Permanently purge recipes          | No       | No            | Yes               |
| Access full audit log              | No       | Limited       | Yes               |

Editor accounts must begin through normal registration and email verification, then be promoted by the Administrator. Only the Administrator may appoint or demote Editors.

# 6. Recipe Lifecycle and Moderation

## 6.1 States

- Draft
- Published
- Unpublished
- Flagged / Pending Moderation
- Blocked / Temporarily unavailable
- Soft Deleted
- Purged

.
- **LIFE-001 --- Immediate publication** *(Launch)* Verified users may publish completed recipes immediately; pre-publication approval is not required.
- **LIFE-002 --- Unpublish** *(Launch)* Owners may unpublish their recipes. Unpublished recipes leave search/browse and cannot be newly added to cookbooks, but cookbooks that already referenced them retain access with a notice.
- **LIFE-003 --- Blocked content** *(Launch)* Recipes blocked by moderation become unavailable publicly and through existing cookbooks.
- **LIFE-004 --- Soft deletion** *(Launch)* Editor deletion normally marks a recipe Soft Deleted rather than permanently removing it. Only the Administrator may purge it.
- **LIFE-005 --- Cookbook tombstone** *(Launch)* After permanent purge, existing cookbooks retain a "Recipe no longer available" placeholder until the cookbook owner removes it.

## 6.2 Reverse Moderation
- **MOD-001 --- Flag eligibility** *(Launch)* Only logged-in users may flag recipes or images. A user may flag the same item only once while the flag remains active.
- **MOD-002 --- Flag reason** *(Launch)* A flag must include a controlled reason and may include explanatory text.
- **MOD-003 --- First independent flag** *(Launch)* After one valid flag, the recipe enters moderation, is visually obscured/blurred, and displays an explicit "I understand --- show recipe" action. It remains intentionally viewable.
- **MOD-004 --- Second independent flag** *(Launch)* After a second active flag from a different user, the recipe is removed from normal public access and public cookbook access pending moderation.
- **MOD-005 --- Moderator decisions** *(Launch)* Editors/Admins may approve, edit and approve, block, or soft-delete flagged content.
- **MOD-006 --- Flag abuse** *(Launch)* Repeated clearly invalid or abusive flagging may lead to warning, flagging restrictions or account suspension. A single rejected flag must not automatically penalize the reporter.
- **MOD-007 --- Moderation email** *(Launch)* Owners receive email for flagging, moderation decisions, restoration, blocking/deletion and material moderator edits.

# 7. Recipe Data Model

Each recipe JSON must contain enough structured information to render, scale, search, export and moderate the recipe without parsing presentation text.

| **Area** | **Required / Supported Fields** |
|----|----|
| Identity | recipeId, ownerUserId, schemaVersion, status, createdAt, modifiedAt |
| Presentation | title, description, difficulty, notes, source/attribution |
| Classification | categories\[\], tags\[\], dietaryTags\[\], allergens\[\] |
| Yield | baseQuantity, yieldLabel/type |
| Timing | prep, cook, optional additional durations, calculated total |
| Ingredients | groups\[\], structured entries with quantities, units, canonical ingredient IDs and metadata |
| Instructions | ordered steps with Markdown and optional ingredient references |
| Media | up to three image references; first is hero |
| Derived references | rating summary, popularity/index data may be cached but remain rebuildable |

- **REC-001 --- Required publication fields** *(Launch)* A recipe must have title, description, at least one category, base yield quantity/label, at least one ingredient and at least one instruction step before publication.
- **REC-002 --- Optional source** *(Launch)* Recipes may store original source/author/publication, URL and attribution note separately from the site contributor.
- **REC-003 --- Notes** *(Launch)* Recipes may include a general Notes/Tips section for substitutions, storage, variations and serving information.
- **REC-004 --- Difficulty** *(Launch)* Optional difficulty values initially include Easy, Moderate and Challenging.
- **REC-005 --- Categories** *(Launch)* Recipes may belong to multiple controlled primary categories. Initial examples: Breakfast, Lunch, Dinner, Appetizer, Soup, Salad, Side Dish, Bread, Dessert, Beverage.
- **REC-006 --- General tags** *(Launch)* Tags are flexible and include cuisine and descriptive concepts. Existing tags are suggested first; users may add a new tag only when needed.
- **REC-007 --- Dietary tags** *(Launch)* Dietary tags are controlled and manually selected at launch. Automatic inference is future functionality.
- **REC-008 --- Allergens** *(Launch)* Declared allergens are stored separately from dietary tags and displayed prominently near the top of the recipe.

# 8. Ingredients, Measurements and Conversion {#ingredients-measurements-and-conversion}

## 8.1 Structured Ingredient Entries
- **ING-001 --- Structured fields** *(Launch)* Ingredients must be stored as quantity, optional upper quantity/range, unit, canonical ingredient ID/name, preparation/state note, requirement type, scaling flag and group reference rather than one unstructured string.
- **ING-002 --- Requirement types** *(Launch)* Ingredient entries support Required, Optional and Garnish. Optional/Garnish do not count as missing required ingredients in What Can I Make?.
- **ING-003 --- Ingredient groups** *(Launch)* Recipes may contain named ingredient groups; one unnamed default group is available by default.
- **ING-004 --- Reordering** *(Launch)* Ingredients and ingredient groups preserve user-defined order and must be reorderable.
- **ING-005 --- Non-scaling values** *(Launch)* Ingredients such as "salt to taste" or "oil as needed" may be marked non-scaling.
- **ING-006 --- Ranges** *(Launch)* Ranges such as 2--3 tbsp scale both ends.

## 8.2 Yield and Scaling
- **SCL-001 --- Numeric base yield** *(Launch)* Recipes define a numeric base yield plus a label/type, such as 6 servings, 24 cookies, 2 loaves, 3 jars or 2 litres.
- **SCL-002 --- Mathematical scaling** *(Launch)* Scaled quantities use the mathematically correct ratio. Countable ingredients may display results such as 2½ eggs rather than forced rounding.
- **SCL-003 --- Readable fractions** *(Launch)* Display favours familiar cooking fractions such as ⅛, ¼, ⅓, ½, ⅔ and ¾ while retaining calculation-safe internal numeric values.

## 8.3 Unit Systems
- **UNIT-001 --- Supported systems** *(Launch)* Metric and US/customary cooking measurements are supported.
- **UNIT-002 --- Profile preference** *(Launch)* Logged-in users store a preferred measurement system in their profile; recipe pages also provide a temporary Metric/US override.
- **UNIT-003 --- Anonymous default** *(Launch)* Anonymous visitors default to Metric and Celsius, reflecting the site's Canadian default configuration.
- **UNIT-004 --- Direct conversions** *(Launch)* Mathematically reliable conversions such as g↔oz, kg↔lb, mL↔fl oz, L↔volume units and cm↔in may be automatic.
- **UNIT-005 --- Ingredient-aware volume/weight** *(Launch)* Volume-to-weight conversion must use ingredient-specific density/conversion data. The site must never assume one universal cup-to-gram value.
- **UNIT-006 --- Practical rounding** *(Launch)* Converted quantities should use kitchen-practical rounding and avoid misleading decimal precision.
- **TEMP-001 --- Temperature conversion** *(Launch)* Fahrenheit/Celsius conversion is automatic and rounded to practical cooking values, e.g. 350°F ≈ 175°C. Original entered value remains authoritative.

## 8.4 Ingredient Dictionary
- **DICT-001 --- Canonical IDs** *(Launch)* Ingredients use permanent canonical IDs to support synonyms, regional names, conversion data and reliable matching.
- **DICT-002 --- Autocomplete** *(Launch)* Recipe entry and What Can I Make? use ingredient autocomplete. Existing canonical ingredients are preferred before new creation.
- **DICT-003 --- Synonyms** *(Launch)* Editors may manage synonyms and regional equivalents such as green onion/scallion/spring onion and chickpea/garbanzo bean.
- **DICT-004 --- Specificity** *(Launch)* Broad ingredient categories can broaden search but must not falsely satisfy specific requirements. Generic "cheese" does not imply Parmesan.
- **DICT-005 --- Preparation state** *(Launch)* Preparation such as chopped/sliced/diced is metadata and does not create separate ingredients.
- **DICT-006 --- Fresh/dried/frozen** *(Launch)* Fresh, dried and frozen forms remain associated with the same canonical ingredient, with state metadata where relevant.
- **DICT-007 --- Directional raw/cooked rules** *(Launch)* Where state materially matters, matching may be directional: raw chicken may satisfy a future cooked requirement; cooked chicken cannot satisfy a specifically raw requirement.
- **DICT-008 --- Ingredient categories** *(Launch)* Canonical ingredients may be classified into grocery groups such as Produce, Meat & Poultry, Seafood, Dairy, Baking, Grains, Pasta, Condiments, Oils, Herbs & Spices, Frozen and Beverages.
- **DICT-009 --- Unverified ingredients** *(Launch)* Users may create missing ingredients during recipe entry. New ingredients are immediately usable, marked Unverified, and queued for Editor review.
- **DICT-010 --- Merging** *(Launch)* Editors may merge duplicate ingredient records after a preview of affected recipes and metadata. Merges must not break existing recipes.

# 9. Recipe Creation and Editing
- **EDIT-001 --- Guided form** *(Launch)* Normal recipe creation uses a guided form, not raw JSON editing.
- **EDIT-002 --- Drafts** *(Launch)* Users may save incomplete private drafts and return later.
- **EDIT-003 --- Autosave** *(Launch)* Recipe creation/editing periodically autosaves and displays a subtle Saving/Saved status.
- **EDIT-004 --- Ingredient entry defaults** *(Launch)* The standard ingredient row shows quantity, unit, ingredient autocomplete and preparation/note. Advanced options reveal requirement type, scale/non-scale and state metadata.
- **EDIT-005 --- Ingredient groups** *(Launch)* Creation begins with one unnamed ingredient group and offers Add Ingredient Group.
- **EDIT-006 --- Instruction steps** *(Launch)* Instructions use Add Step, automatic numbering, reordering and limited sanitized Markdown.
- **EDIT-007 --- Ingredient-step linking** *(Launch)* Authors may optionally associate ingredients or ingredient groups with instruction steps.
- **EDIT-008 --- Time entry** *(Launch)* Prep and Cook fields appear by default. Additional time types such as Rest, Chill, Marinate, Rise, Cool, Freeze and Soak are added through a plus control. Values may be entered as 90 minutes or 1 hour 30 minutes.
- **EDIT-009 --- Total time** *(Launch)* Component durations are normalized and summed for natural display, e.g. 135 minutes displays as 2 hours 15 minutes.
- **EDIT-010 --- Preview** *(Launch)* Users may preview the finished recipe before publishing.
- **EDIT-011 --- Immediate owner edits** *(Launch)* Edits to a published recipe take effect when the owner saves them.
- **EDIT-012 --- No full revision history** *(Launch)* The site does not maintain a full user-facing recipe revision history.
- **EDIT-013 --- Moderator edit notice** *(Launch)* Material moderator edits are audited, may trigger email, and may show the owner a notice that the recipe was edited by a moderator.
- **EDIT-014 --- Duplicate warning** *(Launch)* Potentially similar titles should trigger a duplicate warning but never block legitimate alternative versions.
- **EDIT-015 --- Markdown** *(Launch)* Descriptions, instructions and notes may use limited sanitized Markdown; arbitrary HTML must not execute.

# 10. Search, Discovery and What Can I Make?

## 10.1 Search and Browsing
- **SEA-001 --- Search fields** *(Launch)* Search covers title, description, ingredients, contributor username, categories, tags and dietary tags.
- **SEA-002 --- Ingredient include/exclude** *(Launch)* Users may search for recipes containing specified ingredients and exclude specified ingredients.
- **SEA-003 --- Filters** *(Launch)* Search/browse filters include category, general tags, dietary tags, allergens to exclude, difficulty, prep time, total time, rating and contributor.
- **SEA-004 --- Sorts** *(Launch)* Selectable sorts: Highest Rated, Most Popular, Alphabetically, Quickest and Difficulty.
- **SEA-005 --- Pagination** *(Launch)* Normal pagination is required; recipes per page is configurable.
- **SEA-006 --- Autocomplete** *(Launch)* Homepage search may suggest recipe names, ingredients, tags and categories.

## 10.2 What Can I Make?
- **WCM-001 --- Launch requirement** *(Launch)* What Can I Make? is required for Phase 1 launch.
- **WCM-002 --- Temporary ingredient list** *(Launch)* Users build a temporary list using autocomplete. The initial release does not store a permanent personal pantry.
- **WCM-003 --- Match groups** *(Launch)* Results are grouped as You Can Make This, Almost There, and Partial Match.
- **WCM-004 --- Almost There threshold** *(Launch)* Almost There means no more than approximately 20% of required ingredients are missing.
- **WCM-005 --- Missing display** *(Launch)* Results should show match counts such as 8 of 10 required ingredients and identify missing required ingredients.
- **WCM-006 --- Optional/garnish handling** *(Launch)* Optional and garnish ingredients do not reduce match completeness.
- **WCM-007 --- Quantity agnostic** *(Launch)* Launch matching cares whether an ingredient is present, not how much of it is available.
- **WCM-008 --- Pantry staples** *(Launch)* The site may assume configurable staples such as water, salt, black pepper and basic cooking oil. Users can toggle individual staple assumptions off.
- **WCM-009 --- Canonical matching** *(Launch)* Matching uses canonical ingredient IDs, synonyms, specificity rules and applicable state rules rather than only exact text.

# 11. Ratings, Popularity and Recently Viewed
- **RATE-001 --- One rating per user** *(Launch)* Logged-in users may rate 1--5 stars, with one active rating per recipe per user.
- **RATE-002 --- Rating changes** *(Launch)* A user may change their rating; the new value replaces the old value rather than adding another vote.
- **RATE-003 --- Self-rating** *(Launch)* Recipe authors may rate their own recipes.
- **RATE-004 --- Aggregate display** *(Launch)* Recipe pages display average rating and number of ratings.
- **RATE-005 --- Deleted accounts** *(Launch)* Ratings survive deletion of the contributing account and continue to affect aggregate scores.
- **STAT-001 --- Page views** *(Launch)* Recipe page views are tracked separately from recipe JSON and may be buffered/batched.
- **STAT-002 --- Duplicate suppression** *(Launch)* Basic duplicate view suppression should count approximately one eligible view per visitor/session per recipe within a configured window.
- **STAT-003 --- Most Popular** *(Launch)* Page-view statistics determine Most Popular sorting and homepage prominence.
- **STAT-004 --- Recently viewed** *(Launch)* Logged-in users may see a Recently Viewed Recipes section. Associated history is removed upon account deletion.

# 12. Images and Media
- **IMG-001 --- Image count** *(Launch)* Each recipe may contain up to three images.
- **IMG-002 --- Formats** *(Launch)* PNG, JPG/JPEG and WEBP are supported.
- **IMG-003 --- Limits** *(Launch)* Launch upload limit: maximum 1024×1024 pixels and 5 MB per recipe image.
- **IMG-004 --- Hero image** *(Launch)* The first uploaded image is the hero image and is used for primary recipe display, cards, cookbook PDF and social metadata.
- **IMG-005 --- No Image placeholder** *(Launch)* Recipes may publish without images and use a standard "No Image" placeholder.
- **IMG-006 --- Thumbnails** *(Launch)* Thumbnails should fit within 150×150 pixels while preserving aspect ratio.
- **IMG-007 --- Generated accessibility text** *(Launch)* Authors are not asked to write custom alt text. The system generates safe contextual text such as "Image of Chocolate Cake".
- **IMG-008 --- Image moderation** *(Launch)* Flagged images are obscured while under review and may be restored or removed by moderators.
- **IMG-009 --- Storage** *(Launch)* Images remain ordinary media files referenced by recipe JSON; they are not embedded as base64 data in authoritative JSON.

# 13. Cookbooks
- **CB-001 --- Eligibility** *(Launch)* Only logged-in users may create and save cookbooks.
- **CB-002 --- Primary saving mechanism** *(Launch)* Cookbooks replace separate Favourites, Collections and Saved Recipes systems.
- **CB-003 --- Add to Cookbook** *(Launch)* Every eligible recipe page provides Add to Cookbook, showing existing cookbooks plus Create New Cookbook.
- **CB-004 --- Private by default** *(Launch)* Cookbooks are private by default.
- **CB-005 --- Static share link** *(Launch)* Owners may enable a difficult-to-guess static public link viewable by anyone without an account; only the owner may edit.
- **CB-006 --- No duplicates** *(Launch)* The same recipe may not appear more than once in the same cookbook.
- **CB-007 --- Storage by reference** *(Launch)* Cookbook JSON references recipe IDs rather than duplicating full recipe content.
- **CB-008 --- Cookbook fields** *(Launch)* Cookbooks store ID, title, description, owner, dates, recipe references, custom sections, per-recipe yield settings, cookbook measurement preference, sharing token and optional cover image.
- **CB-009 --- Default organization** *(Launch)* Default presentation sorts by category alphabetically and then recipe title alphabetically.
- **CB-010 --- Custom sections** *(Launch)* Owners may create and maintain custom cookbook sections.
- **CB-011 --- Per-recipe scaling** *(Launch)* A cookbook may remember a different serving/yield quantity for each referenced recipe.
- **CB-012 --- Measurement preference** *(Launch)* A cookbook stores its own Metric/US preference, initially inherited from the owner profile but independently changeable.
- **CB-013 --- Unpublished recipe** *(Launch)* If a recipe is later unpublished by its owner, existing cookbooks retain access with a notice; it cannot be newly added.
- **CB-014 --- Flagged/blocked recipe** *(Launch)* A recipe temporarily blocked after moderation thresholds, or formally blocked, becomes unavailable through public/shared cookbook access while unavailable.
- **CB-015 --- Deleted recipe** *(Launch)* Purged recipes leave a "Recipe no longer available" placeholder that the cookbook owner may remove.
- **CB-016 --- No duplication** *(Launch)* Duplicate Cookbook is not provided.
- **CB-017 --- No fixed limits** *(Launch)* No initial fixed limit on cookbooks per user or recipes per cookbook, subject to reasonable anti-abuse safeguards.

# 14. Export, Print and PDF
- **EXP-001 --- Individual recipe PDF** *(Launch)* Visitors may export a recipe to PDF using the currently selected yield, measurement system and converted temperatures.
- **EXP-002 --- Print Recipe** *(Launch)* Recipe pages provide a browser Print Recipe option with a print-friendly layout that omits unnecessary navigation/interface elements.
- **EXP-003 --- Cookbook PDF** *(Launch)* Cookbook PDF includes cover, title/description, table of contents, recipe sections, recipe pages and alphabetical recipe index.
- **EXP-004 --- Cookbook TOC** *(Launch)* Table of contents lists recipe name and page number; only the hero image is included for each recipe.
- **EXP-005 --- Recipe JSON export** *(Launch)* Recipe JSON export is a launch feature and includes a schema version identifier.
- **EXP-006 --- Recipe JSON import** *(Launch)* Logged-in users may import validated supported recipe JSON. Imported recipes become owned by the importer and cannot overwrite another user's recipe by supplying an existing ID.
- **EXP-007 --- Import preview** *(Launch)* Imported recipes may be previewed and saved as Draft before publication.
- **EXP-008 --- Portable package direction** *(Launch)* A future portable recipe package may use ZIP to bundle recipe JSON plus images; image binaries should not be embedded inside JSON.
- **EXP-009 --- Ratings excluded from personal recipe export** *(Launch)* Ratings are site activity and are not normally included in an individual user recipe export.

# 15. Responsive Interface, Themes and Accessibility
- **UI-001 --- Responsive site** *(Launch)* One responsive interface must support desktop, laptop, tablet and phone.
- **UI-002 --- Desktop efficiency** *(Launch)* Desktop is an important primary usage context and should make recipe management and editing efficient.
- **UI-003 --- Mobile priorities** *(Launch)* Search, recipe viewing, scaling, unit switching, Add to Cookbook, What Can I Make? and Cooking Mode must work especially well on phones.
- **UI-004 --- Desktop navigation** *(Launch)* Primary desktop navigation: Home, Browse Recipes, What Can I Make?, My Cookbooks, Add Recipe, with account controls separately accessible.
- **UI-005 --- Mobile navigation** *(Launch)* Mobile uses a bottom navigation pattern for Home, Search, What Can I Make?, Cookbooks and Account. Add Recipe remains prominently accessible without occupying a permanent bottom slot.
- **UI-006 --- Recipe cards** *(Launch)* Cards show thumbnail, title, rating, total time, difficulty, primary categories and contributor where space permits; avoid clutter.
- **UI-007 --- Allergen prominence** *(Launch)* Declared allergens appear prominently near the top of recipe pages and do not rely on colour alone.
- **UI-008 --- Serving controls** *(Launch)* Recipe pages offer − / current yield / + controls plus direct numeric entry.
- **UI-009 --- Measurement switch** *(Launch)* Recipe pages provide a visible temporary Metric/US switch.
- **UI-010 --- Cooking Mode** *(Launch)* Cooking Mode presents one step at a time with large readable text, step number, Previous/Next controls, and associated ingredients where available.
- **UI-011 --- No wake lock requirement** *(Launch)* Keeping the screen awake is not required at launch.
- **UI-012 --- Recipe text size** *(Launch)* Recipe and Cooking Mode interfaces include direct text-size controls independent of browser zoom.
- **UI-013 --- Themes** *(Launch)* Launch includes four themes: two light and two dark. Logged-in users save a default theme in their profile.
- **UI-014 --- Anonymous preferences** *(Launch)* Anonymous preferences are not persisted at launch; administratively configured Canadian defaults are used.
- **A11Y-001 --- WCAG target** *(Launch)* Design toward WCAG 2.2 AA wherever practical, including contrast, keyboard operation, visible focus, semantic structure, labelled fields, accessible errors and sufficiently large targets.
- **A11Y-002 --- Reorder accessibility** *(Launch)* Where drag-and-drop exists, Move Up/Down or equivalent keyboard-accessible alternatives should be available.
- **JS-001 --- JavaScript** *(Launch)* JavaScript may be required for the full interactive experience. Public recipe content should remain server-rendered/readable where practical for accessibility and search indexing.

# 16. SEO and Public Indexing
- **SEO-001 --- Index public recipes** *(Launch)* Published recipes are indexable by public search engines; drafts, unpublished, blocked, soft-deleted and private administrative content are not intentionally indexed.
- **SEO-002 --- Human-readable URLs** *(Launch)* Published recipes use human-readable URLs while retaining permanent internal recipe IDs. Redirect handling should preserve links after slug/title changes.
- **SEO-003 --- Recipe structured data** *(Launch)* Published recipe pages expose appropriate recipe structured metadata for name, description, contributor, images, times, yield, ingredients, instructions, rating and relevant dietary data when available.
- **SEO-004 --- Page metadata** *(Launch)* Recipe pages provide suitable title, meta description, canonical URL and social-sharing metadata.
- **SEO-005 --- No duplicate index variants** *(Launch)* Measurement or serving-size switches must not create separately indexed duplicate pages for the same recipe.

# 17. Administration Dashboard

## 17.1 Dashboard and Moderation

- **ADM-001 --- Dashboard summary** *(Launch)* Admin home shows total/published/draft/unpublished recipes, users, active/suspended users, Editors, pending moderation, flagged images, unverified ingredients and site-health warnings.
- **ADM-002 --- Activity statistics** *(Launch)* Dashboard may show recipe views, new users, new recipes and ratings for the current month.
- **ADM-003 --- Unified moderation area** *(Launch)* Moderation uses one area with tabs for Recipes, Images, Ingredients and User/Account Issues.
- **ADM-004 --- Recipe management** *(Launch)* Editors/Admins have a searchable/filterable/paginated recipe table with title, owner, status, categories, dates, rating and active flags.
- **ADM-005 --- User management** *(Launch)* Administrator has a searchable user table with username, real name, email, role, status, creation date and last login.
- **ADM-006 --- Private notes** *(Launch)* Editors/Admins may attach private moderation notes to user accounts.
- **ADM-007 --- Suspensions** *(Launch)* Account suspension requires a reason and supports indefinite or date/time-limited suspension.
- **ADM-008 --- Account deletion** *(Launch)* Administrator may delete user accounts with audit reason. Personal information is removed, recipes transfer to Former Member, ratings remain and cookbooks/sharing links are deleted.
- **ADM-009 --- Inactive accounts** *(Launch)* Maintenance identifies ordinary accounts not logged in for 12 months. Purge is review-driven, not blind automatic deletion. Administrator/Editor/protected/held accounts are excluded from automatic eligibility.

## 17.2 Taxonomy and Ingredient Management
- **ADM-010 --- Category/tag management** *(Launch)* Editors/Admins may create, rename, merge and disable categories/tags, with usage counts shown before impactful changes.
- **ADM-011 --- Ingredient administration** *(Launch)* Ingredient screen shows canonical name/ID, synonyms, category, verification, conversion data, recipe usage and modification date.
- **ADM-012 --- Merge preview** *(Launch)* Ingredient merges require a preview of affected recipes, synonyms and metadata conflicts plus explicit confirmation.

## 17.3 Backups, Maintenance and Health
- **ADM-013 --- Backups page** *(Launch)* Administrator sees backups with date, type, size, status and download action, plus Create Backup Now.
- **ADM-014 --- Export Site Data** *(Launch)* Administrator has a prominent full site export function requiring fresh password verification.
- **ADM-015 --- Maintenance tools** *(Launch)* Maintenance includes Rebuild Search Index, Validate Recipe Files, Validate User Files, Validate Ingredient Data, Recalculate Ratings, Check Missing Images, Check Orphaned Images/Files, Check Broken Recipe/Cookbook/Ingredient References, Identify Inactive Accounts and Purge Approved Old Accounts.
- **ADM-016 --- Orphan cleanup** *(Launch)* Potential orphan files are listed with evidence and require explicit Administrator confirmation before deletion.
- **ADM-017 --- Audit log interface** *(Launch)* Administrator can filter audit events by date, moderator, action and target, and export JSON or CSV.
- **ADM-018 --- Health panel** *(Launch)* Where detectable, show last backups, last index rebuild, directory writability, email status, cron/scheduled task status, disk usage and recent validation errors.
- **ADM-019 --- No impersonation** *(Launch)* There is no Log in as User feature.
- **ADM-020 --- Server-side authorization** *(Launch)* Administrative and Editor permissions must be enforced server-side, not merely by hiding links.

# 18. Backup, Recovery, Concurrency and Maintenance

## 18.1 Atomic Writes and Conflicts

- **DATA-001 --- Atomic writes** *(Launch)* Authoritative JSON writes must use a safe temporary-write/validate/replace pattern wherever technically possible.
- **DATA-002 --- File locking** *(Launch)* Concurrent writes to recipes, users, ratings, cookbooks, moderation, ingredient and index files must use file locking or equivalent protection.
- **DATA-003 --- Recipe edit conflicts** *(Launch)* If a recipe changed since an editor loaded it, saving an older copy must warn rather than silently overwrite the newer version.
- **DATA-004 --- Autosave conflicts** *(Launch)* Conflicting autosaves/drafts across tabs/devices must warn rather than auto-merge.
- **DATA-005 --- Concurrent ratings** *(Launch)* Simultaneous ratings must not lose votes, duplicate a user rating or corrupt JSON.
- **DATA-006 --- Immediate index updates** *(Launch)* Recipe changes update search/discovery indexes immediately or near-immediately.
- **DATA-007 --- Manual rebuild** *(Launch)* Administrator can rebuild generated indexes without modifying authoritative recipe content.

## 18.2 Backup Schedule
| **Backup Type** | **Retention** | **Images Included** |
|----|----|----|
| Daily | 7 most recent | No, structured data primarily |
| Weekly | 4 most recent | Not required unless host mechanism includes them |
| Monthly | 6 most recent | Yes; full media-inclusive recovery point |

- **BKP-001 --- Backup download** *(Launch)* Administrator may download available backups.
- **BKP-002 --- Manual backup** *(Launch)* Administrator may create an additional backup on demand.
- **BKP-003 --- Backup consistency** *(Launch)* Backup/export generation should coordinate with file locking/atomic writes to avoid archiving partial JSON states.

## 18.3 Full Site Export and Restore
- **MIG-001 --- Full site export** *(Launch)* Administrator can create a portable archive containing authoritative site data, configuration, uploaded media and optionally rebuildable indexes.
- **MIG-002 --- Sensitive export verification** *(Launch)* A full site export requires the Administrator to freshly re-enter/verify their password.
- **MIG-003 --- Manual restore only** *(Launch)* There is no web-based automatic restore. Restores are performed manually through WHC/cPanel/File Manager/SFTP/SSH or comparable hosting tools.
- **MIG-004 --- Post-restore maintenance** *(Launch)* After a manual restore, maintenance tools can validate files and rebuild indexes/derived rating data as necessary.
- **MIG-005 --- Portability** *(Launch)* Migration to another compatible host fundamentally consists of transferring application files, data, media, updating environment configuration, checking permissions and rebuilding derived indexes if required.

# 19. Security, Privacy and Abuse Controls
- **SEC-001 --- Sensitive data protection** *(Launch)* User email addresses, password hashes, reset/verification tokens, backups, moderation records and audit logs must not be directly web-accessible.
- **SEC-002 --- Fresh auth for sensitive actions** *(Launch)* Account deletion and full-site export may require fresh password verification even during a Remember Me session.
- **SEC-003 --- Three-stage self-deletion** *(Launch)* User self-deletion requires: (1) Are you sure? confirmation; (2) exact typed DELETE_ACCOUNT; (3) final permanent-deletion confirmation.
- **SEC-004 --- Former Member transfer** *(Launch)* Retained recipes from deleted accounts transfer to a system account displayed as Former Member.
- **SEC-005 --- Cookbook removal on deletion** *(Launch)* Deleting an account deletes its cookbooks and invalidates public cookbook links.
- **SEC-006 --- Suspension separation** *(Launch)* Suspending an account blocks authenticated actions but does not automatically remove previously legitimate published recipes.
- **SEC-007 --- Email-only notifications** *(Launch)* Launch notifications are email-based only; there is no notification bell/inbox.
- **SEC-008 --- No noisy notifications** *(Launch)* Do not email for ratings, rating changes, page views, cookbook views or Add to Cookbook activity.
- **SEC-009 --- Markdown sanitization** *(Launch)* User Markdown must be sanitized; arbitrary HTML/script execution is prohibited.
- **SEC-010 --- Reasonable rate/abuse protections** *(Launch)* Registration, login, reset, flagging, rating and other write-heavy endpoints should permit implementation of reasonable anti-abuse/rate limits compatible with shared hosting.

# 20. Configuration
Normal site configuration should be managed through structured Administrator forms backed by protected file-based configuration. The initial administration interface will not include a raw JSON editor.

| **Configuration Area** | **Examples** |
|----|----|
| General | Site name, description, URL, support email, language |
| Display | Default theme, available themes, default measurement system, recipes per page |
| Media | Image size/dimension limits, thumbnail size, allowed formats |
| Recipes | Difficulty values, categories, dietary tags, allergens, pantry staples |
| Moderation | Flag thresholds, flag reasons, abuse settings |
| Accounts | Username cooldown, reset-token lifetime, Remember Me duration, verification settings |
| Email/CAPTCHA | Sender information and service configuration/secrets |
| Backups | Retention/scheduling where app-controlled |

- **CFG-001 --- Canadian defaults** *(Launch)* Initial anonymous defaults are Metric, Celsius, English and the Administrator-selected default light theme.
- **CFG-002 --- No raw JSON editor** *(Launch)* If direct config file edits are ever necessary, they are performed outside the web application using normal file tools.

# 21. Phase 1 --- Launch Scope

Phase 1 is the complete public recipe platform and administration foundation. The following items are launch requirements, not optional future ideas.

- Public recipe browsing, search, filters, sorting and pagination.
- Open account registration with CAPTCHA and email verification.
- User, Editor and Administrator roles with server-side authorization.
- Public contributor profiles with avatar/About Me and published recipes.
- Guided recipe creation, drafts, autosave, preview and immediate owner edits.
- Structured ingredients, ingredient groups, scaling, fractions, ranges and non-scaling entries.
- Metric and US measurements, temperature conversion and ingredient-specific volume-to-weight conversion where reliable data exists.
- Ingredient dictionary with autocomplete, synonyms, regional names, categories, verification and merge tools.
- Multiple recipe categories, general tags, dietary tags and allergens.
- What Can I Make? with temporary ingredient entry, pantry staples and 20% Almost There threshold.
- 1--5 star ratings with one active vote per user per recipe and changeable ratings.
- Page-view popularity and Recently Viewed Recipes for logged-in users.
- Up to three recipe images, hero image, thumbnails, No Image placeholder and image moderation.
- Individual recipe PDF export, browser print, recipe JSON import/export.
- Private cookbooks, static public sharing links, custom sections, per-recipe scaling and cookbook PDF export.
- Reverse moderation with one-flag warning/blur and two-independent-flag temporary removal.
- Email notifications for verification, reset, account/security changes and recipe moderation events.
- Responsive desktop/tablet/mobile interface, Cooking Mode, text-size controls and four themes.
- WCAG 2.2 AA design target and accessible keyboard alternatives for reorder controls.
- Human-readable recipe URLs and search-engine recipe structured data.
- Atomic writes, file locking, conflict detection, generated indexes and maintenance rebuild tools.
- Administration dashboard, moderation queues, user management, ingredient/taxonomy tools, backups, maintenance, audit log and health panel.
- 7 daily / 4 weekly / 6 monthly backup retention, monthly media-inclusive backups, full site export and manual restore model.

# 22. Phase 2 --- Meal Planning and Shopping Lists

Phase 2 intentionally builds on the structured ingredient and cookbook architecture from Phase 1. These features are not required for launch.

- Date/calendar-based meal planning.
- Assigning recipes to meals/days.
- Per-meal serving quantities.
- Combined shopping lists generated from planned recipes.
- Combining equivalent ingredients where units/conversions permit.
- Shopping-list calculations respecting per-recipe scaling.
- Marking ingredients already on hand.
- Printable and PDF shopping lists.
- Optional persistent pantry/inventory concepts if later desired.

# 23. Future Enhancements

| **Enhancement** | **Notes** |
|----|----|
| Manual nutrition information | Recipe schema should permit later manually supplied nutrition fields; automatic calculation is not a launch feature. |
| AI-assisted dietary/allergen suggestions | May suggest labels later, but launch remains contributor-selected to avoid false assurances. |
| Ingredient substitutions | Canonical ingredient dictionary may later contain substitute relationships. |
| Quantity-aware pantry matching | What Can I Make? could later consider actual quantities. |
| Portable recipe package | ZIP-style package containing JSON plus images. |
| Wake lock in Cooking Mode | May later keep screen awake where browser support permits. |
| Automatic nutrition assistance | External/AI tools may later help populate manual nutrition fields. |
| Ingredient index in cookbook | Alphabetical recipe index only at launch; ingredient index may be added later. |

# 24. Conceptual File Map

The exact physical layout is a technical-design decision, but the following conceptual structure reflects the agreed data boundaries.

| **Conceptual Path** | **Purpose** |
|----|----|
| /data/recipes/\<recipe-id\>\_rec.json | Authoritative recipe record |
| /data/users/\<user-id\>.json | Authoritative user account record |
| /data/ratings/\<recipe-id\>\_rating.json | Authoritative per-recipe ratings |
| /data/cookbooks/\<cookbook-id\>.json | Authoritative cookbook definition |
| /data/ingredients/\... | Canonical ingredient records and/or shards |
| /data/moderation/\... | Flags, moderation queue/state |
| /data/audit/\... | Administrative audit records |
| /data/statistics/\... | Buffered/aggregated recipe view data |
| /data/indexes/search\... | Generated search/browse index |
| /data/indexes/users\... | Generated username/email→user ID lookup |
| /data/indexes/ingredients\... | Generated autocomplete/synonym lookup |
| /data/config/site-config.json | Protected site configuration, excluding secrets where separately stored |
| /assets/recipes/\... | Recipe source images and generated thumbnails |
| /assets/profiles/\... | Uploaded profile avatars |
| /backups/\... | Protected scheduled/manual backup archives |

## 24.1 Example Recipe JSON Shape (Conceptual Only)

*This is not implementation code; it illustrates the agreed logical structure and relationships.*

```
{
  "schemaVersion": 1,
  "recipeId": "rec_...",
  "ownerUserId": "usr_...",
  "status": "published",
  "title": "Example Recipe",
  "description": "...",
  "categories": ["Dinner", "Soup"],
  "tags": ["Canadian", "Comfort Food"],
  "dietaryTags": [],
  "allergens": ["Milk"],
  "yield": {"quantity": 6, "label": "servings"},
  "times": {"prepMinutes": 45, "cookMinutes": 90, "totalMinutes": 135},
  "difficulty": "Moderate",
  "ingredientGroups": [
    {"name": null, "ingredients": [
      {"quantity": 1.5, "unit": "cup", "ingredientId": "ing_...",
        "note": "chopped", "requirement": "required", "scales": true}
      ]}
    ],
  "instructions": [ {"step": 1, "markdown": "...", "ingredientIds": ["ing_..."]} ],
  "images": ["..."],
  "source": {"name": "...", "url": "...", "note": "..."},
  "notesMarkdown": "...",
  "createdAt": "...",
  "modifiedAt": "..."
}
```

# 25. Acceptance Checklist
The following checklist can be used later to confirm that an implementation plan covers the agreed requirements.

- [ ] Public users can browse, search, filter, sort and view published recipes without an account.
- [ ] Registration requires CAPTCHA and verified email.
- [ ] Passwords are securely hashed and user real names remain private.
- [ ] Only owners edit their recipes; Editors/Admins can moderate/edit all recipes according to permissions.
- [ ] Recipe data is stored without an application database and remains portable between compatible hosts.
- [ ] Recipe ingredients are structured and support scaling, ranges, non-scaling entries and groups.
- [ ] Metric/US conversion and temperature conversion work from user/profile or page-level preferences.
- [ ] Ingredient dictionary powers autocomplete, synonyms and What Can I Make?.
- [ ] What Can I Make? groups results into complete, Almost There and partial matches and uses the 20% missing threshold.
- [ ] Users can rate recipes 1--5 stars once and change their existing vote.
- [ ] Page views support popularity without rewriting recipe JSON on every visit.
- [ ] Recipes allow up to three images and display a No Image placeholder when necessary.
- [ ] Users can create private cookbooks, share them with static links and export cookbook PDFs.
- [ ] Unpublished, flagged, blocked and purged recipes behave correctly inside cookbooks.
- [ ] Recipe and cookbook PDF/print output respects selected serving/yield and measurement system.
- [ ] Recipe JSON import/export validates schema and ownership rules.
- [ ] One flag obscures/warns; two independent flags temporarily remove normal access pending moderation.
- [ ] Account deletion uses three confirmations and transfers retained recipes to Former Member.
- [ ] Four themes, responsive design, Cooking Mode and text-size controls are available.
- [ ] Accessibility is designed toward WCAG 2.2 AA.
- [ ] Published recipes use human-readable URLs and recipe structured metadata.
- [ ] Atomic writes, file locking and stale-edit detection prevent common file-storage data loss scenarios.
- [ ] Generated indexes can be rebuilt from authoritative data.
- [ ] Admin dashboard includes moderation, recipe/user/ingredient management, backups, maintenance, audit log and health status.
- [ ] Backup retention is 7 daily, 4 weekly and 6 monthly; monthly backups include media.
- [ ] Full site export includes persistent data and media, requires fresh Administrator authentication, and restore remains manual.
- [ ] Phase 2 meal planning/shopping features are kept outside launch scope but supported by the Phase 1 data model.

# Appendix A --- Key Product Decisions
- Cookbooks are the only personal recipe-saving/collection system at launch; there is no separate Favourites feature.
- Cuisine is represented through general tags rather than a separate cuisine taxonomy.
- Categories handle course/type concepts such as Dinner, Lunch, Soup and Dessert.
- Ingredient quantities are mathematically accurate rather than rounded merely for convenience.
- Volume-to-weight conversion is ingredient-specific and approximate where appropriate.
- Dietary and allergen labels are contributor-selected at launch rather than automatically inferred.
- The What Can I Make? ingredient list is temporary at launch; no persistent pantry is stored.
- One recipe flag creates a warning/blur; two independent flags remove normal access until moderation.
- Recipe owners may unpublish without breaking existing cookbook references.
- Editor deletion is soft deletion; Administrator purge is permanent.
- Images are included in monthly backups, not required in each daily/weekly backup.
- Raw web-based configuration JSON editing and automatic site restore are intentionally excluded.
- The platform is designed for conventional shared hosting rather than infrastructure-heavy deployment.

# Appendix B --- Email Events
| **Email Event**                                    | **Required at Launch** |
|----------------------------------------------------|------------------------|
| Account verification                               | Yes                    |
| Password reset                                     | Yes                    |
| New email verification                             | Yes                    |
| Important account/security changes                 | Yes                    |
| Recipe flagged                                     | Yes                    |
| Moderation decision / restored / blocked / deleted | Yes                    |
| Material moderator edit to owner recipe            | Yes                    |
| New rating / rating change                         | No                     |
| Recipe view                                        | No                     |
| Shared cookbook view                               | No                     |
| Add to Cookbook activity                           | No                     |
