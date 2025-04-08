CSS Validator
=============

![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/crisnao2/css_validator/ci.yml?branch=main) ![GitHub release (latest by date)](https://img.shields.io/github/v/release/crisnao2/css_validator) ![Docker Pulls](https://img.shields.io/docker/pulls/crisnao2/css-validator) ![GitHub](https://img.shields.io/github/license/crisnao2/css_validator)

📖 About the Project
--------------------

The **CSS Validator** is a tool for validating CSS stylesheets, built on top of the official W3C CSS Validator ([CSS Validator](https://github.com/w3c/css-validator)). It allows you to check if your CSS adheres to defined standards (such as CSS3), identifying errors and warnings efficiently. This project provides a simple API that can be run locally or in a CI/CD environment, supporting CSS validation via HTTP requests.

The project is packaged as a Docker image, making it easy to deploy in various environments. It also integrates with Semantic Release for automated versioning and publishing to Docker Hub.

### Features

*   CSS validation based on W3C standards.
*   Support for different validation profiles (e.g., `css3svg`).
*   JSON responses for easy integration with other tools.
*   Automated testing with PHPUnit.
*   Automatic publishing of versioned Docker images to Docker Hub.

* * *

🚀 Getting Started
------------------

### Prerequisites

*   **Docker**: Ensure Docker is installed on your system. You can install it by following the instructions at [docker.com](https://docs.docker.com/get-docker/).
*   **curl** (optional): For testing the API via the command line.

### Running the Docker Image

1.  **Run the container**:
    
        docker run --rm -p 8080:8080 crisnao2/css-validator:latest
    
    This starts the container and maps port `8080` on the container to port `8080` on the host.
    
2.  **Test the API**:
    
    The API will be available at `http://localhost:8080`. See the usage examples below.
    

* * *

📋 Available Parameters
-----------------------

The API accepts the following parameters via `POST` requests:

| Parameter | Description                     | Possible Values             | Default   |
|-----------|---------------------------------|-----------------------------|-----------|
| `css`     | The CSS code to validate.       | String (e.g., `body { color: blue; }`) | Required  |
| `profile` | The CSS validation profile to use. | `css1`, `css2`, `css21`, `css3`, `css3svg`, etc. | `css3svg`    |
| `lang`    | The language for error messages. | `en`, `pt-BR`, `de`, etc.      | `en`      |

* * *

🛠️ Building a Custom Image
---------------------------

You can build a custom version of the Docker image by adjusting the build behavior with the following arguments:

### Build Arguments

| Argument          | Description                                                                 | Possible Values | Default |
|-------------------|-----------------------------------------------------------------------------|-----------------|---------|
| `RUN_TESTS`       | Determines if tests should be run during the build (useful for local builds). | `true`, `false` | `true`  |
| `REMOVE_TEST_FILES` | Determines if test-related files (e.g., `composer`, `vendor/bin/phpunit`) should be removed the image. | `true`, `false` | `false` |

### Steps to Build a Custom Image

1.  **Clone the Repository**:
    
        git clone https://github.com/crisnao2/css_validator.git
        cd css_validator
    
2.  **Build the Custom Image**:
    *   To build the image without running tests and keeping test files:
        
            docker build --build-arg RUN_TESTS=false --build-arg REMOVE_TEST_FILES=false -t css-validator:custom .
        
    *   To build an optimized image (without test files):
        
            docker build --build-arg RUN_TESTS=false --build-arg REMOVE_TEST_FILES=true -t css-validator:custom .
        
3.  **Run the Image**:
    
        docker run -d -p 8080:8080 css-validator:custom
    

* * *

📦 Usage Examples
-----------------

The API accepts `POST` requests with the parameters described above. Below are examples of validating invalid and valid CSS using `curl`.

### Example 1: Validating Invalid CSS

    curl -X POST \
      -d "css='body { color: blue; } p { margin: ; }'" \
      -d "profile=css3svg" \
      -d "lang=en" \
      http://localhost:8080

#### Expected Response

    {
        "cssvalidation": {
            "uri": "file:/tmp/css_xxx.css",
            "checkedby": "http://jigsaw.w3.org/css-validator/",
            "csslevel": "css3",
            "date": "2025-04-06T12:35:13Z",
            "validity": false,
            "errors": [
                {
                    "source": "file:/tmp/css_xxx.css",
                    "line": 1,
                    "context": "p",
                    "type": "generator.unrecognize",
                    "errortype": "parse-error",
                    "errorsubtype": "unrecognized",
                    "message": "Value Error : margin (nullbox.html#propdef-margin)\n\nParse Error"
                }
            ],
            "warnings": [],
            "result": {
                "errorcount": 1,
                "warningcount": 0
            }
        }
    }

### Example 2: Validating Valid CSS

    curl -X POST \
      -d "css='body { color: blue; } p { margin: 10px; }'" \
      -d "profile=css3svg" \
      -d "lang=en" \
      http://localhost:8080

#### Expected Response

    {
        "cssvalidation": {
            "uri": "file:/tmp/css_xxx.css",
            "checkedby": "http://jigsaw.w3.org/css-validator/",
            "csslevel": "css3",
            "date": "2025-04-06T12:35:13Z",
            "validity": true,
            "errors": [],
            "warnings": [],
            "result": {
                "errorcount": 0,
                "warningcount": 0
            }
        }
    }

* * *

🧪 Running Tests
----------------

The project includes automated tests using PHPUnit. To run the tests locally:

1.  **Build the image with test files**:
    
        docker build --build-arg RUN_TESTS=true --build-arg REMOVE_TEST_FILES=true -t css-validator:test .
    
2.  **Run the tests**:
    
        docker run --rm css-validator:test sh -c "composer install --no-interaction && ./vendor/bin/phpunit --configuration phpunit.xml"
    

Tests are automatically executed in the CI pipeline (GitHub Actions) for every push or pull request to the `main` branch.

* * *

🤝 Contributing
---------------

Contributions are welcome! Follow the steps below to contribute:

1.  Fork the repository.
2.  Create a branch for your feature or bug fix:
    
        git checkout -b feature/new-feature
    
3.  Make your changes and commit them:
    
        git commit -m "feat: add new feature"
    
4.  Push your changes to the remote repository:
    
        git push origin feature/new-feature
    
5.  Open a Pull Request on GitHub.

### Commit Conventions

The project uses Semantic Release for automated versioning. Follow the [Angular commit conventions](https://github.com/angular/angular/blob/main/CONTRIBUTING.md#commit) to ensure proper versioning:

*   `feat:` for new features (increments the minor version).
*   `fix:` for bug fixes (increments the patch version).
*   `BREAKING CHANGE:` for breaking changes (increments the major version).

* * *

## License

This project is licensed under the GPL-3.0 License.

## Author

Cristiano Soares
- Website: [comerciobr.com](https://comerciobr.com)

## Support

If you encounter any problems or have any questions, please open an issue on the GitHub repository.