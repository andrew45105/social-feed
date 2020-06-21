Social Feed, which aggregates info about your favourite Instagram and VK accounts.

First, you need your own vk application with client_id, client_secret and redirect_uri. You can create it here - https://vk.com/editapp?act=create
 After this you must make other steps:
- `composer install`
- type your client_id, client_secret and redirect_uri in `.env` file
```
VK_CLIENT_ID=someid
VK_CLIENT_SECRET=somesecret
VK_REDIRECT_URI=http://mysite.local/vk/token/
```
- `bin/console doctrine:database:create`
- `bin/console doctrine:schema:update --force`
- `bin/console doctrine:fixtures:load`
- login at index page `login:admin`, `pass:000000`
- add your favourite instagram/vk accounts and then check Instagram & VK Feed