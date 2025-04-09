### Qquick checklist to verify your recipe:

1. **Start with a clean Drupal site** (or reset an existing one).
2. Enable the required modules:
   - `block`
   - `block_content`
   - `default_content` (if not auto-enabled)
3. Place your `extra_footer` folder in the appropriate recipe location (based on your tooling):
   - If you're using `drupal-recipes`: under `recipes/`
   - If you're using a custom module or install profile: adjust paths accordingly
4. Run:
   ```bash
   drush recipe ../recipes/extra_footer
   ```

5. Visit the site:
   - Confirm the “About Us” block appears in the correct region
   - Confirm the block contains the expected text


https://git.drupalcode.org/project/distributions_recipes/-/blob/1.0.x/docs/recipe_author_guide.md

install
```sh
./vendor/bin/drush recipe ../recipes/extra_footer
```

list all blocks
```
ddev drush php:eval '
foreach (\Drupal\block_content\Entity\BlockContent::loadMultiple() as $id => $block) {
  echo "$id: " . $block->label() . "\n";
}'
```

```
./vendor/bin/ddev: No such file or directory
codespace@drupal-codespaces-stack-web:/var/www/html/cms$ ./vendor/bin/drush php:eval '
foreach (\Drupal\block_content\Entity\BlockContent::loadMultiple() as $id => $block) {
  echo "$id: " . $block->label() . "\n";
}'
```

```
ddev exec "cd cms && ./vendor/bin/drush php:script ../../scripts/list_blocks.php"
1: About
```

