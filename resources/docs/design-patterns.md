# Design Patterns

Design patterns are reusable solutions to common software design problems. They provide a way to solve issues in your code's structure while promoting maintainability and scalability. Design patterns are categorised into three groups:

1) **Creational Patterns**
   These are concerned with object creation.

   - SINGLETON
   - FACTORY
   - ABSTRACT FACTORY
   - BUILDER
   - PROTOTYPE

2) **Structural Patterns**
   These patterns focus on the composition (structure) of classes or objects.

   - ADAPTER PATTERN
   - DECORATOR PATTERN
   - FACADE PATTERN
   - COMPOSITE PATTERN
   - PROXY PATTERN

3) **Behavioral Patterns**
   These patterns deal with object interaction and responsibility distribution.

   - OBSERVER PATTERN
   - STRATEGY PATTERN
   - TEMPLATE METHOD PATTERN
   - COMMAND PATTERN
   - ITERATOR PATTERN

These design patterns are global and programming-language agnostic, however, each programming language has its own way of implementing them. In the explanations of all these design patterns below, i provide examples in PHP. If you program in another language, you just have to learn how each of the patterns are implemented in that language. I can assure you that the approach is generally always the same, with syntactical differences of course.

---

## 1) Creational Design Patterns

Creational design patterns deal with object creation mechanisms, trying to create objects in a manner suitable to the situation. These patterns provide flexibility in how objects are instantiated and constructed. By using these creational patterns, you can better manage object creation processes in your applications, ensuring efficiency, flexibility, and maintainability.

The following are the patterns in this group:

- SINGLETON
- FACTORY
- ABSTRACT FACTORY
- BUILDER
- PROTOTYPE

---

### SINGLETON PATTERN

The singleton pattern ensures that a class has only one instance and provides a global point of access to it. It's commonly used for shared resources like databases or logging.

**PHP example**

```php
class DatabaseConnection {
    // Static property to hold the single
    // instance
    private static $instance = null;

    // Prop to hold the PDO connection
    private $connection;

    // Database connection credentials
    private $host = 'localhost';
    private $db_name = 'my_database';
    private $username = 'root';
    private $password = 'password';

    // Constructor is private to prevent
    // direct creation of object
    private function __construct() {
        try {
            // Create the database
            // connection using PDO
            $this->connection =
                new PDO(
                    "mysql:host=$this->host;dbname=$this->db_name",
                    $this->username,
                    $this->password);

            $this->connection
                ->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION);
        }
        catch (PDOException $e) {
            // Handle any connection error
            echo "Database connection failed: " . $e->getMessage();
        }
    }

    // Public static method to get the
    // single instance of the class
    public static function getInstance()
    {
        // If no instance exists, create one
        if (self::$instance === null) {
            self::$instance
                = new DatabaseConnection();
        }

        // Return the single instance
        return self::$instance;
    }

    // Method to get the PDO connection
    public function getConnection() {
        return $this->connection;
    }
} // End of singleton class
```

**Using the Singleton to connect to the database**

```php
// Get the single instance of the class
$db1 =
    DatabaseConnection::getInstance();

// Retrieve the PDO connection from the
// singleton
$connection = $db1->getConnection();

// Perform a database query
$query = $connection->query(
    "SELECT * FROM users"
);

// Fetch the results
$results = $query->fetchAll(
    PDO::FETCH_ASSOC
);

print_r($results);
```

**Key Points:**

- Private constructor: Ensures no external code can directly create an instance of the class. Only the class itself can create an instance.
- Static getInstance() method: This is the only way to get the single instance of the class. It checks if the instance already exists; if not, it creates one.
- Database connection: The __construct() method handles the connection using PDO (PHP Data Objects), which is a modern way of connecting to databases in PHP. Placeholder credentials (localhost, root, password) are used here for demonstration.
- When you call the getInstance() static method of DatabaseConnection, it checks if an instance of the class already exists. If it doesn't, the class creates an instance and establishes the database connection.
- All subsequent calls to getInstance() will return the same instance with the same connection.
- This ensures that only one database connection is used throughout the application, which can improve performance and prevent multiple connections from being opened unnecessarily.

**Practical Use**

This pattern is commonly used in situations like managing a single database connection in a web application, where multiple database connections could lead to inefficiency and resource exhaustion.

---

### FACTORY PATTERN

The factory pattern provides a way to create objects without specifying the exact class. It delegates the object creation process to subclasses or another object.

**PHP example:**

```php
interface Animal {
    public function speak();
}

class Dog implements Animal {
    public function speak() {
        return 'Woof';
    }
}

class Cat implements Animal {
    public function speak() {
        return 'Meow';
    }
}

class AnimalFactory {
    public static function
            createAnimal($type) {
        if ($type == 'dog') {
            return new Dog();
        } elseif ($type == 'cat') {
            return new Cat();
        }
    }
}
```

**Using the factory class**

```php
$animal =
    AnimalFactory::createAnimal('dog');
echo $animal->speak();
```

---

### ABSTRACT FACTORY PATTERN

The Abstract Factory Pattern provides an interface for creating families of related or dependent objects without specifying their concrete classes. It allows the client to create objects that are part of a specific family, where the exact class of each object is determined by the factory.

**Example Scenario:**

Let's say you're building an application that can work with two types of user interfaces: Windows and Mac. Each UI has its own specific elements (like buttons, checkboxes), and the abstract factory will help you create the appropriate family of UI components based on the environment.

**PHP Code Example**

```php
// Abstract Factory interface
interface GUIFactory {
    public function createButton();
    public function createCheckbox();
}

// Concrete Factory for Windows
class WindowsFactory
                    implements GUIFactory {
    public function createButton() {
        return new WindowsButton();
    }

    public function createCheckbox() {
        return new WindowsCheckbox();
    }
}

// Concrete Factory for Mac
class MacFactory
                    implements GUIFactory {
    public function createButton() {
        return new MacButton();
    }

    public function createCheckbox() {
        return new MacCheckbox();
    }
}

// Abstract Product for Button
interface Button {
    public function render();
}

// Concrete Button for Windows
class WindowsButton
                    implements Button {
    public function render() {
        echo "Rendering Windows Button\n";
    }
}

// Concrete Button for Mac
class MacButton implements Button {
    public function render() {
        echo "Rendering Mac Button\n";
    }
}

// Abstract Product for Checkbox
interface Checkbox {
    public function check();
}

// Concrete Checkbox for Windows
class WindowsCheckbox
                    implements Checkbox {
    public function check() {
        echo "Checking Windows Checkbox\n";
    }
}

// Concrete Checkbox for Mac
class MacCheckbox
                    implements Checkbox {
    public function check() {
        echo "Checking Mac Checkbox\n";
    }
}

// Client code
function renderUI(
                    GUIFactory $factory) {
    $button
        = $factory->createButton();

    $checkbox
        = $factory->createCheckbox();

    // Render button and checkbox
    $button->render();
    $checkbox->check();
}

// Example of using the abstract
// factory to render Windows UI
$windowsFactory
    = new WindowsFactory();
renderUI($windowsFactory);

// Example of using the abstract
// factory to render Mac UI
$macFactory = new MacFactory();
renderUI($macFactory);
```

**Key points**

- Abstract Factory pattern allows creating families of related objects.
- You can easily switch between different families (e.g., Windows vs Mac).

---

### BUILDER PATTERN

The Builder Pattern is used to create complex objects step by step. It separates the construction of a complex object from its representation, allowing the same construction process to create different representations.

**Example Scenario:**

Consider building a 'House' with various customisable features like walls, doors, windows, and roof. Each house may differ in terms of these features, but the construction process is the same.

**PHP Code Example:**

```php
// Product: House
class House {
    public $walls;
    public $doors;
    public $windows;
    public $roof;

    public function show() {
        echo "House with
            {$this->walls} walls,
            {$this->doors} doors,
            {$this->windows} windows,
            and a {$this->roof} roof\n";
    }
}

// Builder Interface
interface HouseBuilder {
    public function buildWalls();
    public function buildDoors();
    public function buildWindows();
    public function buildRoof();
    public function getHouse();
}

// Concrete Builder: Wooden House
class WoodenHouseBuilder
                implements HouseBuilder {
    private $house;

    public function __construct() {
        $this->house = new House();
    }

    public function buildWalls() {
        $this->house->walls
            = "Wooden";
    }

    public function buildDoors() {
        $this->house->doors
            = "Wooden";
    }

    public function buildWindows() {
        $this->house->windows
            = "Wooden";
    }

    public function buildRoof() {
        $this->house->roof
            = "Wooden";
    }

    public function getHouse() {
        return $this->house;
    }
}

// Director
class ConstructionEngineer {
    private $houseBuilder;

    public function __construct(
            HouseBuilder $houseBuilder) {
        $this->houseBuilder
            = $houseBuilder;
    }

    public function constructHouse() {
        $this->houseBuilder
            ->buildWalls();
        $this->houseBuilder
            ->buildDoors();
        $this->houseBuilder
            ->buildWindows();
        $this->houseBuilder
            ->buildRoof();

        return $this->houseBuilder
            ->getHouse();
    }
}

// Client code
$builder =
    new WoodenHouseBuilder();
$engineer = new
    ConstructionEngineer($builder);

$house =
    $engineer->constructHouse();
$house->show();
```

**Key points:**

- The Builder Pattern focuses on step-by-step construction of complex objects.
- The same building process can create different results (e.g., WoodenHouse or BrickHouse).

---

### PROTOTYPE PATTERN

The Prototype Pattern involves creating new objects by copying an existing object (the prototype). This is useful when the cost of creating a new object is expensive, and you can avoid it by cloning an existing object.

**Example Scenario:**

Imagine you are creating a 'Document Editor' where you need to create copies of documents. The prototype pattern allows you to create new documents by cloning existing ones instead of creating them from scratch.

**PHP Code Example:**

```php
// Prototype interface
interface DocumentPrototype {
    public function clone();
}

// Concrete Prototype: TextDocument
class TextDocument
        implements DocumentPrototype {

    private $content;

    public function __construct(
            $content)
    {
        $this->content = $content;
    }

    // Clone the document
    public function clone() {
        return new TextDocument(
            $this->content);
    }

    // Display document content
    public function showContent() {
        echo "Document content: " .
            $this->content . "\n";
    }
}

// Client code
$originalDocument =
    new TextDocument(
        "Original Content");
$clonedDocument =
    $originalDocument->clone();

// Display the contents of both the
// original and the cloned document
$originalDocument->showContent();
$clonedDocument->showContent();
```

**Key points:**

- Prototype Pattern allows creating new objects by copying an existing object.
- It is useful for cases where object creation is expensive, and copying is more efficient.

---

## 2) Structural Design Patterns

Structural design patterns focus on how objects and classes are composed (structured). They help ensure that if one part of a system changes, the entire structure doesn't need to change. These structural patterns help organize and manage the relationships between classes and objects in your applications, providing flexibility, reusability, and better organization. The following design patterns fall under this group:

- ADAPTER PATTERN
- DECORATOR PATTERN
- FACADE PATTERN
- COMPOSITE PATTERN
- PROXY PATTERN

---

### ADAPTER PATTERN

The Adapter Pattern allows objects with incompatible interfaces to work together. It acts as a bridge between two interfaces that otherwise couldn't interact directly. This is useful when you want to integrate a class with a different interface into your system.

**Example Scenario:**

Imagine you have a Media Player that only supports playing MP3 files, but you want to add support for playing MP4 files. The adapter pattern helps convert the MP4 interface to be compatible with the MP3 player.

**PHP code example**

```php
// Target interface
// (the one we want to use)
interface MediaPlayer {
    public function play($filename);
}

// To-be-adapted class (class with
// incompatible interface)
// lets refer to it as the 'source' class
class MP4Player {
    public function playMP4($filename)
    {
        echo "Playing MP4 file: " .
            $filename . "\n";
    }
}

// Adapter class that converts the
// source's interface to the Target
// interface
class MediaAdapter
            implements MediaPlayer {

    private $mp4Player;

    public function __construct(
            MP4Player $mp4Player) {
        $this->mp4Player
            = $mp4Player;
    }

    // Implement the play method of
    // MediaPlayer by using MP4Player's
    // method
    public function play($filename) {
        $this->mp4Player
            ->playMP4($filename);
    }
}

// Client code
function playMedia(
        MediaPlayer $player, $filename)
{
    $player->play($filename);
}

// Example usage
$mp4Player = new MP4Player();
$adapter =
    new MediaAdapter($mp4Player);

// Use the adapter to play an MP4 file
// with a MediaPlayer interface
playMedia($adapter, "example.mp4");
```

**Key points**

- The Adapter Pattern enables objects with incompatible interfaces to work together.
- You can introduce new functionality to an existing system without changing its structure.

---

### DECORATOR PATTERN

The decorator pattern allows behavior to be added to individual objects, either statically or dynamically, without affecting the behavior of other objects from the same class.

**PHP example:**

```php
// Decorator pattern in PHP
// Coffee interface (Component)
interface Coffee {
    public function getCost();
    public function getDescription();
}

// SimpleCoffee class (Concrete
// Component)
class SimpleCoffee implements Coffee {
    public function getCost() {
        return 10;
    }

    public function getDescription() {
        return "Simple Coffee";
    }
}

// Decorator base class (Optional, used
// for type hinting)
abstract class CoffeeDecorator
                    implements Coffee {
    protected $coffee;

    public function __construct(
                    Coffee $coffee) {
        $this->coffee = $coffee;
    }
}

// MilkDecorator class (Concrete
// Decorator)
class MilkDecorator extends
                    CoffeeDecorator {
    public function getCost() {
        // Add the cost of milk
        return $this->coffee
            ->getCost() + 2;
    }

    public function getDescription() {
        // Add milk to the description
        return $this->coffee
            ->getDescription() . ", Milk";
    }
}

// SugarDecorator class (Concrete
// Decorator)
class SugarDecorator extends
                    CoffeeDecorator {
    public function getCost() {
        // Add the cost of sugar
        return $this->coffee
            ->getCost() + 1;
    }

    public function getDescription() {
        // Add sugar to the description
        return $this->coffee
            ->getDescription() . ", Sugar";
    }
}

// Client code
$coffee = new SimpleCoffee();

// Add milk to the coffee
$coffeeWithMilk = new
MilkDecorator($coffee);

// Add sugar to the coffee with milk
$coffeeWithMilkAndSugar = new
SugarDecorator($coffeeWithMilk);

// Output: 13 (10 + 2 for milk + 1 for
// sugar)
echo $coffeeWithMilkAndSugar
    ->getCost();
echo "\n";

// Output: Simple Coffee, Milk, Sugar
echo $coffeeWithMilkAndSugar
    ->getDescription();
```

**Key points**

- The purpose of the Decorator Pattern is to allow behavior to be added to individual objects dynamically, without affecting the behavior of other objects from the same class. This is done by "wrapping" the object with decorator classes that enhance or modify its functionality.
- The pattern is called a decorator because the decorator class is used to "decorate" or wrap the original class, adding additional features or responsibilities to the object being decorated. Each decorator class implements the same interface or inherits from the same parent class as the original object.
- Here is how the Code really Works:

  **Component Interface (Coffee):**
  - This Coffee interface defines the core functionality, which in this case is getCost() and getDescription().
  - This sets the contract for both the base class (SimpleCoffee) and the decorators.

  **Concrete Component (SimpleCoffee):**
  - This class implements the basic behavior of the Coffee interface. In this case, it represents a simple coffee with a base cost and description.

  **Decorator Classes (MilkDecorator and SugarDecorator):**
  - These decorator classes implement the same Coffee interface but enhance the functionality of the base class (SimpleCoffee). They add their own behavior-in this case flavour (adding milk or sugar) while still calling the base class's methods to maintain the existing functionality.
  - For example, MilkDecorator adds the cost of milk to the base coffee and modifies the description to include milk. Similarly, SugarDecorator adds the cost of sugar and updates the description.

- The decorator pattern also allows for dynamic and flexible behavior addition. You can apply multiple decorators in sequence, as shown in the example where we first decorate the coffee with milk and then with sugar. Each decorator adds to the cost and description, building on top of the previous one.

**Use Case:**

The Decorator Pattern is useful when you want to add functionality to an object without modifying its code, especially when you need to apply different combinations of behavior (e.g., coffee with milk, coffee with sugar, coffee with milk and sugar, etc.). Instead of creating multiple subclasses to represent each combination, you can achieve this through decorators.

This decorator pattern provides flexibility because you can "stack" multiple decorators in any order and combine them as needed without altering the underlying object.

---

### FACADE PATTERN

The facade pattern provides a simplified interface to a complex subsystem. It hides the complexities of the system behind a unified, easy-to-understand interface.

**PHP example:**

```php
// Facade pattern in PHP
class CPU {
    public function freeze() {
        echo "CPU is frozen\n";
    }

    public function jump($position) {
        echo "CPU jumps to $position\n";
    }

    public function execute() {
        echo "CPU is executing instructions\n";
    }
}

class Memory {
    public function load(
                    $position, $data) {
        echo "Loading $data into position $position\n";
    }
}

class HardDrive {
    public function read(
                    $sector, $size)
    {
        echo "Reading $size bytes from sector $sector\n";
    }
}

class ComputerFacade {
    private $cpu;
    private $memory;
    private $hardDrive;

    public function __construct() {
        $this->cpu = new CPU();
        $this->memory =
            new Memory();
        $this->hardDrive =
            new HardDrive();
    }

    public function start() {
        $this->cpu->freeze();
        $this->memory
            ->load(0x1000, "OS");
        $this->hardDrive
            ->read(0x1000, 512);
        $this->cpu->jump(0x1000);
        $this->cpu->execute();
    }
}

// Usage
$computer =
    new ComputerFacade();
$computer->start();
```

**Key points:**

- The Facade Pattern provides a simplified interface to a complex system or a set of classes. It hides the complexity of the subsystems and offers a unified, easier-to-use interface for the client.
- It is called a Facade because it acts as the "front" or "face" of a set of subsystems. Instead of interacting directly with complex subsystems (like CPU, Memory, and HardDrive in this example), the client interacts with a single class (the ComputerFacade) that manages the communication with the subsystems behind the scenes.
- Here is how the Code Works:

  **Subsystem Classes:**
  - The CPU, Memory, and HardDrive are the individual subsystems that perform specific tasks.
  - Each class has its own methods (freeze, jump, load, etc.) that are specific to its responsibility.

  **Facade Class (ComputerFacade):**
  - This class aggregates (beings together) the subsystems (CPU, Memory, and HardDrive) and exposes a simple start() method to the client.
  - The start() method internally coordinates the necessary calls to the subsystems, such as freezing the CPU, loading data into memory, reading from the hard drive, and finally executing instructions.
  - The client only needs to call start(), without worrying about the individual steps required to boot the computer.

**Use Cases:**

- This can be used anywhere to simplify complex systems. When you have a system composed of several intricate components (such as hardware or APIs), a facade can simplify interactions by consolidating them into a single, easy-to-use interface. For example, in this case, starting a computer requires multiple steps, but the facade hides all that complexity.
- It can be used to Reducing Tight Coupling. The client code is only coupled with the facade (ComputerFacade), not with the individual subsystems (CPU, Memory, HardDrive). This makes the code easier to maintain and modify.
- It can also be used to Improve Code Readability. Facade patterns are often used to improve code readability. If the client had to call each subsystem's methods directly, the code would be more complex and harder to read.
- Another great benefit of the facade pattern allows for a clean, organised separation between the client and the complex internals of a system. It also allows for easier maintenance because changes to the subsystem do not affect the client, as long as the facade's interface remains consistent.
- You can see how the Facade Pattern simplifies interactions with a complex system by creating a single entry point for the client to use.

---

### COMPOSITE PATTERN

The Composite Pattern is used to treat individual objects and compositions of objects uniformly. This pattern allows you to build a tree structure where individual objects and groups of objects are handled the same way.

**Example Scenario:**

A company might have employees who can be regular staff members or managers who supervise other employees. The composite pattern helps manage this hierarchy by treating both employees and managers uniformly.

**PHP Example:**

```php
// Component interface
interface Employee {
    public function getDetails();
}

// Leaf class: Represents individual
// employees
class Staff implements Employee {
    private $name;
    private $position;

    public function __construct(
            $name, $position)
    {
        $this->name = $name;
        $this->position = $position;
    }

    public function getDetails() {
        echo "{$this->name} is a {$this->position}\n";
    }
}

// Composite class: Represents
// managers that can have
// subordinates
class Manager implements Employee {
    private $name;
    private $position;
    private $subordinates = [];

    public function __construct(
                    $name, $position) {
        $this->name = $name;
        $this->position = $position;
    }

    // Add a subordinate (can be either
    // staff member or another manager)
    public function add(
                    Employee $employee) {
        $this->subordinates[]
            = $employee;
    }

    public function getDetails() {
        echo "{$this->name} is a
        {$this->position} and has the
        following subordinates:\n";
        foreach (
            $this->subordinates
                            as $employee) {
            $employee->getDetails();
        }
    }
}

// Client code
$manager =
    new Manager("Alice", "CEO");
$staff1 =
    new Staff("Bob", "Developer");
$staff2 =
    new Staff("Charlie", "Designer");

$manager->add($staff1);
$manager->add($staff2);

// Display details of the manager and
// their subordinates
$manager->getDetails();
```

**Key points**

- The Composite Pattern allows you to treat individual objects and groups of objects the same way.
- It is useful for representing hierarchical structures like employees, files, or GUI components.

---

### PROXY PATTERN

The Proxy Pattern provides a placeholder or surrogate for another object to control access to it. It's used to add an extra level of control before accessing the actual object, such as in cases of lazy loading, access control, or logging.

**Example Scenario:**

Imagine you have a large image that takes time to load. Instead of loading it directly, you can use a proxy that loads the image only when it's needed.

**PHP Code Example:**

```php
// Subject interface
interface Image {
    public function display();
}

// RealSubject: The actual large image
// class
class RealImage implements Image {
    private $filename;

    public function __construct(
                    $filename) {
        $this->filename = $filename;
        $this->loadFromDisk();
    }

    // Simulate loading the image
    // from disk
    private function loadFromDisk() {
        echo "Loading image: " .
            $this->filename . "\n";
    }

    public function display() {
        echo "Displaying image: " .
        $this->filename . "\n";
    }
}

// Proxy class: Controls access to
// RealImage
class ProxyImage implements Image
{
    private $realImage;
    private $filename;

    public function __construct(
                    $filename) {
        $this->filename = $filename;
    }

    // Display the image, loading it
    // only if necessary
    public function display() {
        if ($this->realImage === null) {
            // Only load the image if it's
            // not already loaded
            $this->realImage =
                new RealImage(
                    $this->filename);
        }

        $this->realImage->display();
    }
}

// Client code
$image =
    new ProxyImage("large_photo.jpg");

// Image is not loaded yet; only the
// proxy is created
echo "Image proxy created but not loaded yet.\n";

// Now we display the image, which
// triggers loading and displaying
$image->display();
```

**Key points:**

- The Proxy Pattern provides a surrogate to control access to another object.
- It's useful for lazy initialisation, access control, or logging.

---

## 3) Behavioral Design Patterns

Behavioral design patterns focus on how objects communicate and interact with each other. They define the way in which classes and objects collaborate. The following design patterns fall under this group:

- OBSERVER PATTERN
- STRATEGY PATTERN
- TEMPLATE METHOD PATTERN
- COMMAND PATTERN
- ITERATOR PATTERN

---

### OBSERVER PATTERN

The observer pattern is used when there is one subject and multiple observers that depend on the subject's state. Whenever the subject changes its state, it notifies all its observers.

**PHP example:**

```php
// Observer pattern in PHP
class Subject {
    private $observers = [];

    public function addObserver(
            $observer)
    {
        $this->observers[] = $observer;
    }

    public function notify() {
        foreach ($this->observers as
                                $observer) {
            $observer->update();
        }
    }
}

class Observer {
    public function update() {
        echo 'Observer notified';
    }
}

// Usage
$subject = new Subject();
$observer = new Observer();
$subject->addObserver($observer);
$subject->notify();
```

---

### STRATEGY PATTERN

The strategy pattern allows you to define a family of algorithms, encapsulate each one, and make them interchangeable. It lets the algorithm vary independently from the clients that use it. In other words, the clients use the algorithm relevant to them through the family interface.

This pattern needs

- an interface to define the algorithm family
- one or more strategy classes to implement the different algorithms in the family, each in its own way
- one client-interfacing class (often referred to as context) to bring the two (interface and strategies) together, which clients will use to vary their strategies seamlessly.

**PHP example:**

```php
// Strategy pattern in PHP
interface PaymentStrategy {
    public function pay($amount);
}

// Concrete strategy class: PayPal
class PayPalStrategy implements
                    PaymentStrategy {
    public function pay($amount) {
        return "Paid $amount using PayPal";
    }
}

class CreditCardStrategy implements
                    PaymentStrategy {
    public function pay($amount) {
        return "Paid $amount using Credit Card";
    }
}

// Context class that will use the
// PaymentgStrategy interface to select
// the relevant strategy each time
class PaymentContext {
    private $strategy;

    // Inject the chosen strategy into
    // the context
    public function __construct(
            PaymentgStrategy $strategy)
    {
        $this->strategy = $strategy;
    }

    // Execute the chosen strategy's
    // payment method
    public function
            executePayment($amount) {
        return $this->strategy
            ->pay($amount);
    }
}

// Client code

// Choose PayPal as the payment
// strategy
$context = new PaymentContext(
    new PayPalStrategy()
);

// Output: Paid 100 using PayPal
echo $context->executePayment(100);
echo "\n";

// Switch payment strategy to credit
// card
$context = new PaymentContext(
    new CreditCardStrategy());

// Output: Paid 200 using Credit Card
echo $context->executePayment(200);
```

**Key points and explanation**

- The purpose of the Strategy Pattern is to define a family of algorithms (or strategies) that can be used interchangeably. Instead of hardcoding specific behaviour into a class, different strategies are encapsulated in separate classes, allowing the behaviour to be selected at runtime.
- Here is how it works:
  - **Strategy Interface (PaymentStrategy)**
    This interface defines the common method pay($amount) that all concrete strategies must implement. It ensures that all payment methods share the same contract.

  - **Concrete Strategies (PayPalStrategy, CreditCardStrategy):**
    These classes implement the PaymentStrategy interface and provide the specific behaviour for how the payment is processed. For example, PayPalStrategy handles payments using PayPal, while CreditCardStrategy handles payments using a credit card.

  - **Context Class (PaymentContext):**
    The context class (PaymentContext) is responsible for interacting with the chosen strategy. It accepts a PaymentStrategy object as a parameter and uses it to process the payment without knowing the details of how the payment is handled. This decouples the client code from the specific strategies.

**Use Cases:**

- Dynamic Behaviour Selection. When you need to switch between different algorithms or behaviours at runtime, the strategy pattern is useful. For example, choosing different payment methods (like PayPal or credit card) based on the user's preference.
- Avoiding Conditional Logic. Instead of using complex if-else or switch statements to determine the behaviour, the strategy pattern encapsulates these behaviours into separate classes, making the code more maintainable and flexible.
- The big benefit of the Strategy Pattern is that it promotes the open/closed principle (the O in the SOLID principles). It is open for extension in that new strategies can be added without changing the existing code in the PaymentContext. The pattern allows for flexibility by letting you swap out behaviour dynamically while keeping the code structure clean and modular.

---

### TEMPLATE METHOD PATTERN

The Template Method Pattern defines the skeleton of an algorithm in a base class, while allowing subclasses to override specific steps of the algorithm without changing its structure. It is called the Template Method because it provides a template for the overall process, with some steps left open for customisation by subclasses.

**PHP Example:**

```php
// Abstract class with the template
// method
abstract class MealPreparation {

    // Template method
    public function prepareMeal() {
        $this->boilWater();
        $this->cook();
        $this->serve();
    }

    // Common step
    public function boilWater() {
        echo "Boiling water\n";
    }

    // Steps to be implemented by
    // subclasses
    abstract public function cook();

    // Common step
    public function serve() {
        echo "Serving the meal\n";
    }
}

// Concrete class: preparing pasta
class PastaMeal extends
                    MealPreparation {
    public function cook() {
        echo "Cooking pasta\n";
    }
}

// Concrete class: preparing rice
class RiceMeal extends MealPreparation {
    public function cook() {
        echo "Cooking rice\n";
    }
}

// Client code
$pastaMeal = new PastaMeal();
$pastaMeal->prepareMeal();
// Output: Boiling water, Cooking pasta,
// Serving the meal

echo "\n";

$riceMeal = new RiceMeal();

// Output: Boiling water, Cooking rice,
// Serving the meal
$riceMeal->prepareMeal();
```

**Key points**

- The purpose of the template design pattern is that it allows you define the framework of an algorithm in a base class, leaving the details of specific steps to be implemented by subclasses.
- Here is How It Works:

  **Template Method (prepareMeal):**
  This method is defined in the base class (MealPreparation) where the algorithm's structure is defined. Here, some steps (like boilWater and serve) are common, while others (like cook) are left abstract for subclasses to implement.

  There are concrete Classes (PastaMeal, RiceMeal):
  These classes implement the step (cook) in their own way, allowing flexibility while still following the overall process defined by the base class.

**Use Case:**

The Template Method Pattern is useful when multiple classes share a similar process but require customisation for specific steps. In the example, both pasta and rice meals follow the same process but differ in the cooking step.

---

### COMMAND PATTERN

The Command Pattern turns a request into an object, allowing the parameterisation of clients with queues, requests, or logs. It is called Command because each object represents an operation to be executed, stored, or undone.

**PHP Example:**

```php
<?php

// Command interface
interface Command {
  public function execute();
}

// Concrete command: Turn on the light
class LightOnCommand implements Command {
  private $light;

  public function __construct($light) {
    $this->light = $light;
  }

  public function execute() {
    $this->light->turnOn();
  }
}

// Concrete command: Turn off the light
class LightOffCommand implements Command {
  private $light;

  public function __construct($light) {
    $this->light = $light;
  }

  public function execute() {
    $this->light->turnOff();
  }
}

// Receiver class: Light
// called receiver coz its a class that actually
// receives & executes the command sent by
// the command classes behind the scenes
class Light {
  public function turnOn() {
    echo "Light is ON\n";
  }

  public function turnOff() {
    echo "Light is OFF\n";
  }
}

// Invoker class: Remote control
// Called the invoker coz from the client side,
// it sets the ball rolling towards getting the
// command to do its job. It does that in its
// PressButton() method.
class RemoteControl {
  private $command;

  public function setCommand(
               Command $command)
  {
          $this->command = $command;
  }

  public function pressButton() {
    $this->command->execute();
  }
}

// Client code
// prepare the receiver of the command
$light = new Light();

$remote = new RemoteControl();

// Set the required command interface
// (light on or light off)
// Light on
$remote->setCommand(
                   new LightOnCommand($light)
     );

// Output: Light is ON
$remote->pressButton();

// Light off
$remote->setCommand(
                   new LightOffCommand($light)
    );

// Output: Light is OFF
$remote->pressButton();
```

**Key points**

**Purpose of the Command Pattern:**
This pattern encapsulates requests as objects, allowing you to parameterise methods, delay execution, and queue operations. It decouples the invoker (client) from the object that performs the actual work (receiver).

**How It Works:**

**Command Interface:**
- The Command interface defines a method (execute()) that will be implemented by different commands.

**Concrete Commands**
- LightOnCommand,
- LightOffCommand):

  These command classes accept a receiver class Light. This makes sense because their command action is all about light.
  Through their execute() methods, these classes indirectly implement the specific actions (turnOn, turnOff) by delegating the work to the Light receiver class which is the class having these turnOn() and turnOff() methods. Which of them is called will depend on the command interface-so it will be turnOn() or turnOff() for LightOnCommand and LightOffCommand respectively.

**Invoker (RemoteControl):**
The invoker class stores a command and executes it when the client presses a button. The invoker doesn't know the details of what the command does, it simply executes the execute() method on the command.
It is called the invoker because the execution of the command starts from it. It all starts from its PressButton() method. It then runs the execute() method on the command which it had already stored in its '$command' property.

**Use Case:**

The Command Pattern is useful for implementing undo/redo functionality, executing commands in sequence, or logging operations for future execution. In the example, a remote control can switch between different commands (turning the light on or off) without knowing how each command works internally.

---

### ITERATOR PATTERN

The Iterator Pattern provides a way to access the elements of a collection (like an array or list) sequentially without exposing the underlying structure. It is called Iterator because it "iterates" over a collection one element at a time.

**PHP Example:**

```php
<?php
// Iterator pattern in PHP

// Collection interface
interface Collection {
  public function getIterator();
}

// Concrete collection class
class BookCollection implements Collection {
  private $books = [];

  public function addBook($book) {
    $this->books[] = $book;
  }

  public function getIterator() {
    return new BookIterator($this->books);
  }
}

// Iterator class
class BookIterator {
  private $books;
  private $index = 0;

  public function __construct($books) {
    $this->books = $books;
  }

  public function hasNext() {
    return $this->index
            < count($this->books);
  }

  public function next() {
    return $this->books[$this->index++];
  }
}

// Client code
$bookCollection = new BookCollection();
$bookCollection->addBook("Design Patterns");
$bookCollection->addBook("Clean Code");

$iterator = $bookCollection->getIterator();
while ($iterator->hasNext()) {
  echo $iterator->next() . "\n";
}
// Output:
// Design Patterns
// Clean Code
```

**Key points**

**Purpose of the Iterator Pattern:**
This pattern allows clients to traverse through the elements of a collection without needing to know the underlying structure of the collection. It provides a standardised way to access and iterate over data.

**How It Works:**

**Collection Interface (Collection):**
- This defines a method (getIterator()) to return an iterator for the collection.
- Concrete collection classes (like BookCollection) implement this method.

**Iterator Class (BookIterator):**
- The BookIterator class is a class that stands on its own and is used by the BookCollection classes (via their getIterator() methods to which they will pass their array of items-be it books or anything else). It defines methods like hasNext() and next() to access elements in the collection one by one.

**Client Code:**

The client doesn't need to know how the BookCollection stores its books. It just uses the iterator to access the books sequentially using hasNext() and next().

**Use Case:**

The Iterator Pattern is useful when you need to traverse a collection without exposing its internal details. It is especially helpful when working with custom data structures or complex collections. In the example, the BookIterator provides a simple way to loop through a collection of books without directly accessing the array.
