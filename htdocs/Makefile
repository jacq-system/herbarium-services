##	RUN INSIDE THE CONTAINER, that is:
# docker exec -it services bash

app:
	php bin/console app:elastic-collectors  # refresh ES index for collectors

code_management:
	composer phpstan # PHPStan
	composer fixer # PHP CS Fixer
	composer fixit # PHP CS Fixer
