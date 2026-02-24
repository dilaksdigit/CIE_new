provider "aws" { region = "us-east-1" }
resource "aws_instance" "cie_app" { ami = "ami-12345" instance_type = "t2.medium" }
