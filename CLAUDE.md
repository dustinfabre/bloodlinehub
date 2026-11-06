Rules for working on BloodlineHub (Laravel 12, Inertia v2, Vue 3, Tailwind v4, shadcn-vue).

## Pigeon search results

Every pigeon search or picker (search dropdowns, comboboxes, pick lists, checkbox lists for adding birds) shows its results in one standard format, the format of the breeders list on Create Pairing:

- line 1: `{name || 'Unnamed'} - {ring_number || personal_number}`
- line 2: `bloodline • color`
- line 3: `S: {sire ring or name} | D: {dam ring or name}` (a linked loft parent, or else the outside parent recorded on the bird)
- then private notes and remarks, italic and truncated

How:

- Render each result with `resources/js/components/PigeonSearchResult.vue` inside your clickable row. Never hand-write result markup. Page-specific extras go in its slots: `aside` beside the title (badges, gender), the default slot for extra lines underneath (status, availability, "already entered").
- Feed it from `App\Models\Pigeon::searchSummary()`: `->with(Pigeon::SEARCH_RELATIONS)->get(Pigeon::SEARCH_COLUMNS)->map->searchSummary()`, merging in any extra fields the page needs. On a pivot query (e.g. season entries) qualify the columns with `pigeons.`. Type the results as `PigeonSearchItem` from `resources/js/types/pigeon-search.ts`.
- Use theme tokens for the dropdown (`bg-popover`, `hover:bg-accent`, `text-muted-foreground`), not hard-coded light colors.
- `notes` are private: only send them for the viewer's own pigeons. If a search can list other users' birds, drop `notes` for birds the viewer doesn't own.
- New pigeon searches and pickers must follow this rule.
