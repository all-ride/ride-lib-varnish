# ride-lib-varnish

## 1.0.2
- Fixed usage of vcl.inline command

## 1.0.1
- Removed the new line character between secret and challenge when authenticating.
This to comply to the Varnish specifications. 
If your secret worked before this update, you will have to add a _\n_ character to your secret setting. 
Thanks to mitchvdl.

## 1.0.0
- Updated README
- First stable release


