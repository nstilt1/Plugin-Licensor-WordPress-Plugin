# Plugin-Licensor-WordPress-Plugin
This is currently undergoing development. Just trying to connect the licensing back end with the store's back end, then a get request for the license code.

The license creation and fetching should work, but there could be bugs. Right now, the licensing is only implemented for any individual company to sell their own products on their store. It isn't set up for them to easily sell other companies' products on their store. It is possible, and I can make it easier to do that later, but for now, it will mainly be for individual companies or individual developers.

# API Requests

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
```
License: {
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
};
```

# To do:
* ~~add license code to the automated emails that WooCommerce sends~~
* add license code to the order information on the website

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
