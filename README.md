# Plugin-Licensor-WordPress-Plugin
This is currently undergoing development. Just trying to connect the licensing back end with the store's back end, then a get request for the license code.

The license creation and fetching should work, but there could be bugs. Right now, the licensing is only implemented for any individual company to sell their own products on their store. It isn't set up for them to easily sell other companies' products on their store. It is possible, and I can make it easier to do that later, but for now, it will mainly be for individual companies or individual developers.

# To do:
* ~~Create the API call for `create_company` that will be used to register the stores~~
    * Status: function has been deployed. There could be some runtime errors.
* Implement the `create_company` API call
* Create an API call for `create_plugin` that will hold the logic for the actual licenses
* Implement the `create_plugin` API call with a form in the WordPress Plugin
* ~~add license code to the automated emails that WooCommerce sends~~
* ~~add license info to the user profile on the website~~
* ~~finalize the API request to handle the 4/12/2023 adjustments~~


# API Requests

## Register your store
https://4qlddpu7b6.execute-api.us-east-1.amazonaws.com/v1/create_company
### Request Parameters

To register your store, these are the required steps and parameters. You might notice that I have a thing for encryption.

1. Generate a 2048-bit RSA key pair. Store the private key somewhere and the public key needs to be sent in this request.
* Must be a PEM encoded key, and the public key should have this header: `-----BEGIN PUBLIC KEY-----`.
```
{
   "data" (AES encrypted): {
      "key": "your 2048-bit public key goes here",
      "user_first_name": "your first name",
      "user_last_name": "your last name",
      "user_discord": "Your discord handle, because email is soooo last decade.",
      "store_name": "Your Store's name",
   },
   "nonce": "the nonce used for the AES encrypted 'data' parameter",
   "key" (RSA encrypted): "You will need to encrypt the AES key used to encrypt the 'data' parameter with the Plugin Licensor public key.",
   "timestamp": "The time since Epoch in seconds.",
}
```

2. After completing the request, the API will respond with your Company ID. You will need to hang on to that for every other API request.
### Response

The response from registering the store will be like so:

```
{
   "data" (AES encrypted): {
      "company_id": "Your company ID for Plugin Licensor. Keep track of it or else you'll have trouble using the service.",
   },
   "key" (RSA encrypted): "This is the AES key, and it was encrypted with the public key that you sent us.",
   "nonce": "This is the nonce used to encrypt the 'data' parameter",
}
```

## Get License

### Request Parameters

To get license data for a user, the store will make a request with the following parameters:
```
{
    company: String,
    uuid: String,
    timestamp: String,
    signature: String
}
```
The `signature` parameter is a signature of `company + uuid + timestamp`, and the request will not get processed if the timestamp is off by a certain margin.

### Response
The response's body will be encrypted with the store's public key (this probably isn't necessary over HTTPS, but I'm doing it anyway), and the encrypted data will be a JSON string that looks something like this:

#### Update 4/12/2023:
It appears that RSA has a limited amount of data that it can encrypt, so I will instead generate an AES key on the fly, encrypt the license data with that AES key, and then encrypt the AES key with RSA and send the data back like so:
```
{
    License: (AES encrypted) {
        code: "[license code]",
        plugins: [
            {
                id: "PLUGIN ID",
                machines: [
                    {
                        id: "machine ID",
                        computer_name: "Computer Name",
                        os: "Operating system",
                    },
                    ...
                ],
                max_machines: "[machine limit for the license]",
                license_type: "[eg: subscription]",
            },
            ...
        ],
    },
    AES Key: (RSA encrypted),
}
```


# Optimizations
### Elliptic Curve Cryptography
There are probably a lot of optimizations that I could implement, but the single biggest one with the most improvement would be to use elliptic curve cryptography between both my server and the C++ plugin, and between the clients' servers and my server. However, this shouldn't be a big deal unless someone's website is getting loads of traffic and loads of sales. The main reason I didn't implement it is because I generally find it easier to do the encryption that is done, and it would take significantly longer for me to implement that in C++. Another point is that my Lambda functions have to fetch data from and write data to the database already, and that is also a time consuming process. I might use it in the future, but for now, what I have should be good enough.

### AWS Console Optimizations
There are a few things that I know of that I could do to improve the service.
##### DynamoDB Mirroring and Lambda Edge Computing
I could mirror the databases into other regions to reduce latency for users outside of the US, and I could enable edge computing with Lambda to take advantage of the mirroring, but the latency shouldn't be that bad.
##### DynamoDB DAX
DAX should be able to drastically improve performance, but it would only be useful if the service had a ****load of traffic, and it would cost a bit of money.
##### Increase Lambda RAM
I've already used the [AWS Lambda function tuner](https://github.com/alexcasalboni/aws-lambda-power-tuning) on the heaviest Lambda function, the one that encrypts license information to the JUCE clients, but I still need to do that for the other Lambda functions. Also, I could put the memory of them all to the max. Right now, the API call I mentioned uses 1 GB of RAM.

### Limitations
There are some pre-defined limitations by AWS for my server. Some of them can be increased manually, some of them have to be worked around.
##### DynamoDB
* There's a limit of 1000 write units (writes per second) on a DB table item's partition key. I use transactional writes in some of my tables, so that limits it to 500 writes per second for those tables because transactional writes count as 2 write units. This could be worked around by making alternate DB items, so a company could have an ID of `TheirOriginalID-x` where `x` is the number/id that corresponds to which alternative item it is. By using 4 alternative items, the limit should be increased by 4 times. That would also cause there to be 4 items to be read when just trying to get information about the item, such as when doing analytics.
* Also... I use on-demand pricing, so there's a pretty hard but dynamic limit that might make API calls fail if there's a spike in traffic.
##### Lambda
* There's a limit of 1000 concurrent executions which can be increased. This limit is also impacted by how long the Lambda function takes to run. An estimate could be made for the throughput based on the average time it takes, but at 250ms or lower, the throughput should be high enough already. I will get around to "guestimating" the maximum throughput, and I will plot some data in charts on [my company's website](https://www.hyperformancesolutions.com/) once I finish more stuff on my whole to-do list.
