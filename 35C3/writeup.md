Intro
======
For those not interested in the thought process jump to the bottom for the exploit

To the greatest extent possible, my write ups will focus on explaining my thought process from start to finish so that others who may not have a solid foundation can start to build it here. I will try to make all files available as well so that anyone who wants to can solve these with me.

Feel free to provide any corrections, I want to make sure I provide accurate knowledge and I'm still learning as well so I won't always do everything the best way.

Challenge Prompt
------
> PHP's unserialization mechanism can be exceptional. Guest challenge by jvoisin.
> Files at [php.tar](php.tar). Challenge available at `nc 35.242.207.13 1`

## Getting started
The challenge authors were fairly nice with this one - they only provided one _very_ minimal file, seen here
![php code](images/source_code.png)

There are a couple things we can recognize from this file immediately:
1. User input is taken from STDIN on line 3 (trim makes sure start/end whitespace is removed)
2. A class is defined, but never used
    * It has a function to echo the flag
3. There is an odd call to `@unserialize` on user-supplied data

Unserialize in any language should be given special attention, some languages handle it better than others...php is not one of them.

## Research
At this point there were a few questions to start asking:

1. What is unserialize and what does it do with data I give it?

	A quick google search for "php unserialize vulnerability" will turn up a large amount of results like [this](https://www.netsparker.com/blog/web-security/untrusted-data-unserialize-php/). You should be able to quickly find that unserialize can be used for a lot of attacks; remote code execution (RCE), object injection, DoS, etc. But we know the challenge authors provided a class for us and it looks like it will give us the flag so we probably don't need RCE and we definitely don't want to attempt a DoS so lets keep object injection in mind.

2. Why does unserialize have `@` at the beginning

	Unfortunately this is one area that I wasn't really able to find a great answer for at the time. Googling for "php @unserialize" only turned up php's documenation with a 5 year old comment
	![comment](images/unserial.png)
	At the time I failed to recognize this for the hidden gem it is. Remember - we are trying to instantiate a class...and now we know that if that fails, php won't just die on us. It supresses the error and continues. 

	At this point you should start to wonder how this ties together with `__destruct` in the *B* class. I will leave it up to you to research this in-depth, but for a quick run down: Php defines several "[magic methods](https://www.tutorialdocs.com/article/16-php-magic-methods.html)" which will execute when certain conditions are met. `__construct` will execute when an object is created and `__destruct` will execute when it is deleted. In PHP an object will be deleted when there are no more references to the object.

3. The challenge prompt mentions that php is "exceptional." What does that have to do with the `throw new Exception` on line 16

	I didn't know this at the time, but it seems that PHP does not run the `__destruct` method for objects when it throws an Exception. Although I was unable to find the information through research, I was able to come to this conclusion through testing and I'll show you how I got there. 
	
	Additionally, remember that there can't be any reference to the object left in order for `__destruct` to be called. Unfortunately for us, at the bottom of the source code is 
	![end code](images/end.png)
	This is going to keep a reference to whatever object is unserialized until after the echo. However, we never get to the echo because of the Exception and the Exception doesn't call `__destruct`. Unfortunate.

For those interested, [this](https://www.evonide.com/fuzzing-unserialize/) was the article I found most useful during my research and I referred to it constantly.



## Testing

Using a fresh Kali VM I ran `php -f php.php` on the file that was provided. I also added some "sanity checks" to the script - a couple `var_dump` 's to make sure my input was being read correctly and that I made it past unserialize. I also created another file shown below to create valid serialized data and ran it in a similar fashion. There are better ways to do this, this method will require you to re-run the code after every exit, but it worked and was quick.

![serializer](images/serializer.png)

If you didn't know that Exception doesn't call `__destruct` you might assume that all you have to do is provide a serialized *B* object and get the flag. So I tried that and it didn't work.

After some testing I commented out the Exception on line 16 in the challenge script and noticed that the script failed to convert B to a string, removing the echo fixed all issues (The flag message is different because the *B* class in php.php is reading from a flag file) - this is how I discovered that the Exception was blocking an otherwise valid call, first to echo and then to `__destruct` when there were no errors.

![Testing](images/testing.png)
![Testing](images/testing2.png)

(Keep in mind that the php.php shown here has the Exception and the echo commented out)

## Exploitation
After doing all this research and testing we can develop an exploit with these goals in mind
1. We want to create a B object
2. We want the B object to be destroyed or deleted
3. The destruction *must* happen before the Exception. ( Or we need to find a way to bypass / overwrite the Exception class, but that seems unlikely)

If we can get unserialize to create a *B* object, but then produce an error and destroy it *without causing php to fail with an Exception* we can get the flag. But remember the `@`? Anything that would cause a fatal exception in unserialize is being supressed so we can cause as many errors there as we want.

The easiest way I could come up with to produce an error was to make it expect data and then not provide it. A serialized *B* object looks like this `O:1:"B":0:{}` the `0:1:"B":0` tells unserialize to create a *B* object with 0 parameters. 

I simply changed the 0 to a 1. `O:1:"B":1:{}`. Unserialize then creates a B object, attempts to find its parameters and when it doesn't, it throws an exception. Since the `@` is in front of unserialize, the Exception is surpressed and the invalid object is deleted so we call `__destruct`

![Flag](images/exception.png)
(php.php here has the Exception and echo added back in)

Challenge solved.
