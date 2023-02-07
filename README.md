# Plugin-Licensor-WordPress-Plugin
This is currently undergoing development. Just trying to connect the licensing back end with the store's back end, then a get request for the license code.

The license creation and fetching should work, but there could be bugs. Right now, the licensing is only implemented for any individual company to sell their own products on their store. It isn't set up for them to easily sell other companies' products on their store. It is possible, and I can make it easier to do that later, but for now, it will mainly be for individual companies.

# Optimizations
## Elliptic Curve Cryptography
There are probably a lot of optimizations that I could implement, but the single biggest one with the most improvement would be to use elliptic curve cryptography between both the server to the C++ plugin, and between the clients' servers and my server. However, this shouldn't be a big deal unless someone's website is getting loads of traffic and loads of sales. The main reason I didn't implement it is because I generally find it easier to do the encryption that is done, and it would take significantly longer for me to implement that in C++. Another point is that my Lambda functions have to fetch data from and write data to the database already, and that is also a time consuming process. I might use it in the future, but for now, what I have should be good enough.
