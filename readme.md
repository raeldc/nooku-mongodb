MongoDB Support for Nooku Framework
===================================

**Warning: This code is a prototype, incubation stage. Use it at your own risk!**

Instructions
------------

* Clone into your /administrator/components and name it com_mongo. 
* Edit /administrator/components/com_mongo/databases/adapters/document.php, in the _initialize(), enter your MongoDB credentials.
* In your model, extend ComMongoModelDocument

That's it you're ready to go MongoDB!

Notes:
------

* There is still no validation and filtering. Everything you put in the Row object, will be saved in your collection!
* Only basic CRUD and querying is supported for now.
