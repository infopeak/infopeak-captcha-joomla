# InfoPeak Captcha for Joomla

Privacy-first, EU-hosted captcha for Joomla 5 and 6. No tracking, no cookies, no image puzzles: the
visitor's browser solves a small proof-of-work task, and your server checks the result with InfoPeak
before Joomla accepts the form.

One InfoPeak account and one console cover all your sites, whether they run Joomla, WordPress or your
own code. Agencies can manage every client's Captcha account from the
[InfoPeak partner programme](https://partner.infopeak.io/en/).

## Requirements

| | Supported |
|---|---|
| Joomla | 5.0 or later, 6.x |
| PHP | 8.1 or later (Joomla 6 itself requires 8.3) |
| Account | A free InfoPeak Captcha account: 10,000 verifications a month at no cost |

Tested on Joomla 5.0.3 (PHP 8.1), 5.4.10 (PHP 8.3) and 6.2.0 (PHP 8.3, 8.4 and 8.5), in Chromium and WebKit.

## Set up in three steps

1. **Install.** System → Install → Extensions, and upload `plg_captcha_infopeak-<version>.zip`.
   The plugin is switched on automatically.
2. **Enter your keys.** Open System → Plugins → *CAPTCHA - InfoPeak Captcha* and paste the sitekey and
   secret from your [Captcha console](https://infopeak.io/captcha). No account yet? The settings page has a
   button to create one. To try the plugin first, use the public test keys `ipk_test_sitekey` /
   `ipk_test_secret` (any domain, never counted, never for production).
3. **Choose it.** System → Global Configuration → Site → *Default Captcha* → *CAPTCHA - InfoPeak Captcha*.

The status panel at the top of the plugin settings checks your keys with InfoPeak and shows which forms
are protected, so you can see at a glance that everything is in place.

Bind the sitekey to your domain in the Captcha console before going live, so it only works on your site.

## Protected forms

InfoPeak Captcha plugs into Joomla's own captcha system, so it protects exactly the forms where Joomla
offers a captcha:

| Form | Where to choose the captcha |
|---|---|
| User registration | Global Configuration, or Users → Options → *Captcha* |
| Forgot your password | Global Configuration, or Users → Options → *Captcha* |
| Forgot your username | Global Configuration, or Users → Options → *Captcha* |
| Contact forms (com_contact) | Global Configuration, or Contacts → Options → Form → *Captcha* |

The Users and Contacts options can override the global choice, for example to protect only the
contact form.

## Limitations

- **Login is not protected.** Joomla has no captcha on its login form (site or administrator), and this
  plugin does not add one.
- **Privacy requests (com_privacy) are not protected.** Joomla's request form has no captcha field.
- **Other extensions** (form builders, shops, forums, comments) are protected only if they use Joomla's
  captcha system. Many do; check the extension's own captcha setting. Forms that submit with AJAX must
  ask the widget for a fresh token after a rejected submit (`window.infopeakCaptcha.reset()`), because
  tokens are single-use.
- **Forgot your password / username** always show Joomla's neutral message, also when the captcha is
  refused, so the forms cannot reveal which email addresses exist. The request is still stopped.
- **JavaScript is required** to solve the captcha. Without it the form is refused.

## What visitors see when something goes wrong

The plugin fails closed: a form is accepted only when InfoPeak confirms the token.

| Situation | Message |
|---|---|
| Token missing, forged, expired or already used | The spam check was not completed. Please try again. |
| InfoPeak cannot be reached within 8 seconds | The spam check could not be reached. Please try again in a moment. |
| Monthly quota and its reserve are used up | This form cannot check your message right now. Please try again later or contact the site owner. |
| Keys missing or refused by InfoPeak | The spam protection on this form is not set up yet. Please contact the site owner. |

Key problems and unreachable service are also written to Joomla's log (category `plg_captcha_infopeak`).
InfoPeak emails the account owner at 80 % and 100 % of the monthly quota.

## Content Security Policy

If your site sends a Content Security Policy (for example with Joomla's *System - HTTP Headers* plugin),
allow:

```
script-src   https://captcha.infopeak.io
connect-src  https://captcha.infopeak.io
worker-src   blob:
```

The status panel reminds you when Joomla's HTTP Headers plugin has a policy switched on.

## Privacy

- The widget loads from `captcha.infopeak.io` and sets no cookies.
- When a protected form is sent, your server sends the sitekey, secret and token to
  `https://captcha.infopeak.io/api/v1/pow/siteverify`. Nothing about the visitor or the form content is sent.
- When an administrator opens the plugin settings, the plugin checks the keys with the same endpoint,
  using a token that cannot be valid. This uses no quota.
- The links on the settings page carry the administrator's language and `utm_source=joomla_plugin`, and
  nothing else.

## Uninstall

Choose another captcha (or *None*) in Global Configuration and in the Users and Contacts options first.
The plugin refuses to uninstall while a form still uses it, because Joomla cannot show a form whose
captcha plugin is gone. Uninstalling removes the plugin, its settings and its update site.

## Updates

Updates appear in System → Update → Extensions like any other Joomla extension.

## Development

```
plg_captcha_infopeak/   the plugin, exactly as it is packaged
tests/unit.php          answer-handling checks; run inside a Joomla install: php tests/unit.php /path/to/joomla
tools/build.sh          builds build/plg_captcha_infopeak-<version>.zip and writes updates.xml
changelog.xml           shown by Joomla's "Changelog" button
updates.xml             Joomla update server file, generated by tools/build.sh
```

See [RELEASING.md](RELEASING.md) for the release steps.

## Support

support@infopeak.io · [Developer documentation](https://infopeak.io/captcha/developers)

## License

GNU General Public License version 2 or later. See [LICENSE](LICENSE).
