# Plugin-Licensor-WordPress-Plugin
If by any chance that anybody has attempted to integrate their back end with this API, there will be breaking changes in the next version. You will still be able to access the API, but:
1) There will be some server-side security enhancements in the future version
2) The current authentication method is very slow compared to the future version

**If anyone is using this already and wants to use the new features, you should let me know and I might try to create a cross-over API method.** I just wouldn't expect anybody to be using it since there isn't an official integration yet.

The next version will include:
* either 2048-bit or 4096-bit RSA handshake for an initial key exchange
* the remainder of communication will use P-384 or P-521 for ECDH and ECDSA, along with AES-256
* a slight rewrite of some of the back-end to ensure that sensitive/cryptographic data gets zeroed out after use. I'm not a fan of any types of bleeding
* unique encryption keys for each store's database, paired with the standard encryption that the cloud provides already

The next version might include:
* an area in the WordPress plugin with a payment mechanism, likely integrated directly with WooCommerce

The next-next version should include:
* an API method that links your store to your account on the Plugin Licensor website (back at the drawing board)
* a functional dashboard with various stats
