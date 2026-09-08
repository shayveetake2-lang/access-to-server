import re

with open('index.html', 'r', encoding='utf-8') as f:
    content = f.read()

# I will wrap the deploy section (543) and database section (616) into standard-user-portal
# It looks like there is a 3-column grid or similar? Let's check where <div class="grid lg:grid-cols-12 gap-6"> starts and ends.
# I will just wrap them inside standard-user-portal. Actually, I can just add `id="standard-user-portal"` to the container.

# Let's see the lines.
