# wsdl2php
A standalone WSDL to PHP converter script.

This is based heavily (almost entirely) upon the SourceForge wsdl2php project written by Knut Urdalen <knut.urdalen@gmail.com>.

It also includes many enhancements, some of which are present as outstanding tickets on the SourceForge project.

Currently, this is a very simply project.

There are 2 scripts.

``wsdl2php <wsdl>`` and ``allWSDL2PHP``

The first script takes a single parameter to convert a wsdl file or URL into a set of PHP classes.

The output will be saved into the directory ``./Services/ServiceName``.

The second script will look in the WSDLs directory and process all the WSDL files and the contents of the ``wsdl.txt`` file in one batch.

All very much a work in progress. There are some issues, but this is actively used for integration into SalesForce and PostcodeAnywhere, amongst others.

I hope this is of use to you all.

Richard.
